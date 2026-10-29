<?php

namespace Sanjayacloud\Plans\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Sanjayacloud\Plans\Enums\PlanStatus;
use Sanjayacloud\Plans\Models\Plan;
use Sanjayacloud\Plans\PlanResolver;
use Sanjayacloud\Plans\Plans;

/**
 * @property bool $is_super_admin
 * @property int|null $granted_plan_id
 * @property Carbon|null $granted_until
 * @property Carbon|null $trial_ends_at
 */
trait HasPlans
{
    /**
     * The plan resolved for this request, cached so repeated feature checks don't re-query.
     *
     * @var array{plan: ?Plan, status: PlanStatus}|null
     */
    protected ?array $resolvedPlan = null;

    public function initializeHasPlans(): void
    {
        $this->mergeCasts([
            'is_super_admin' => 'boolean',
            'granted_until' => 'datetime',
            'trial_ends_at' => 'datetime',
        ]);
    }

    public function grantedPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'granted_plan_id');
    }

    /**
     * @return array{plan: ?Plan, status: PlanStatus}
     */
    public function resolvedPlan(): array
    {
        return $this->resolvedPlan ??= app(PlanResolver::class)->resolve($this);
    }

    public function forgetResolvedPlan(): static
    {
        $this->resolvedPlan = null;

        return $this;
    }

    public function currentPlan(): ?Plan
    {
        return $this->resolvedPlan()['plan'];
    }

    public function planStatus(): PlanStatus
    {
        return $this->resolvedPlan()['status'];
    }

    public function hasFeature(string $feature): bool
    {
        return $this->currentPlan()?->hasFeature($feature) ?? false;
    }

    /**
     * The current plan's limit for the key, or null when unlimited.
     */
    public function planLimit(string $key): ?int
    {
        return $this->currentPlan()?->limitFor($key);
    }

    public function onPlanTrial(): bool
    {
        return $this->planStatus() === PlanStatus::Trial;
    }

    /**
     * Start (or restart) the no-card trial, using the trial plan's length when no days are given.
     */
    public function startPlanTrial(?int $days = null): bool
    {
        $days ??= Plan::trialPlan()?->trial_days ?? 0;

        if ($days < 1) {
            return false;
        }

        $this->forceFill(['trial_ends_at' => now()->addDays($days)])->save();
        $this->forgetResolvedPlan();

        return true;
    }

    /**
     * Users whose resolved plan status matches, mirroring PlanResolver's order in SQL
     * so admin counts and filters don't have to resolve every user.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWherePlanStatus(Builder $query, PlanStatus|string $status): Builder
    {
        $status = $status instanceof PlanStatus ? $status : PlanStatus::from($status);
        $billsThroughStripe = Plans::billsThroughStripe();

        $validSubscription = fn (Builder $subscriptionQuery) => $subscriptionQuery
            ->where('type', Plans::subscriptionType())
            ->where(fn (Builder $validQuery) => $validQuery->active()->orWhere(fn (Builder $trialQuery) => $trialQuery->onTrial()));

        $withoutSubscription = function (Builder $userQuery) use ($billsThroughStripe, $validSubscription): Builder {
            return $billsThroughStripe ? $userQuery->whereDoesntHave('subscriptions', $validSubscription) : $userQuery;
        };

        $activeGrant = fn (Builder $grantQuery) => $grantQuery
            ->whereNotNull('granted_plan_id')
            ->where(fn (Builder $untilQuery) => $untilQuery->whereNull('granted_until')->orWhere('granted_until', '>', now()));

        if (! $billsThroughStripe && in_array($status, [PlanStatus::Subscribed, PlanStatus::GracePeriod], true)) {
            return $query->whereRaw('1 = 0');
        }

        return match ($status) {
            PlanStatus::Subscribed => $query->whereHas('subscriptions', fn (Builder $subscriptionQuery) => $validSubscription($subscriptionQuery)->whereNull('ends_at')),
            PlanStatus::GracePeriod => $query->whereHas('subscriptions', fn (Builder $subscriptionQuery) => $validSubscription($subscriptionQuery)->onGracePeriod()),
            PlanStatus::Complimentary => $withoutSubscription($query)->where($activeGrant),
            PlanStatus::Trial => $withoutSubscription($query)
                ->whereNot($activeGrant)
                ->where('trial_ends_at', '>', now()),
            PlanStatus::Free => $withoutSubscription($query)
                ->whereNot($activeGrant)
                ->where(fn (Builder $trialQuery) => $trialQuery->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<=', now())),
        };
    }
}
