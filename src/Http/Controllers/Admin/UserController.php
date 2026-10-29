<?php

namespace Sanjayacloud\Plans\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Laravel\Cashier\Subscription;
use Sanjayacloud\Plans\Enums\PlanStatus;
use Sanjayacloud\Plans\Http\Requests\GrantPlanRequest;
use Sanjayacloud\Plans\Http\Requests\UpdateTrialRequest;
use Sanjayacloud\Plans\Models\Plan;
use Sanjayacloud\Plans\Models\PlanAdminAction;
use Sanjayacloud\Plans\Plans;
use Sanjayacloud\Plans\StripeBilling;

class UserController extends Controller
{
    private const AUDIT_LOG_LIMIT = 25;

    public function __construct(private StripeBilling $stripeBilling) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(PlanStatus::class)],
        ]);

        $query = Plans::userQuery()
            ->when(Plans::billsThroughStripe(), fn (Builder $userQuery) => $userQuery->with('subscriptions'))
            ->when($filters['status'] ?? null, fn (Builder $userQuery, string $status) => $userQuery->wherePlanStatus($status))
            ->when($filters['search'] ?? null, fn (Builder $userQuery, string $search) => $userQuery->where(fn (Builder $searchQuery) => $searchQuery
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->latest('id');

        return view('plans::admin.users.index', [
            'users' => $query->paginate(config('plans.admin.per_page', 25))->withQueryString(),
            'statuses' => PlanStatus::cases(),
            'filters' => $filters,
        ]);
    }

    public function show(string $user): View
    {
        $user = $this->findUser($user)->load('grantedPlan');
        $subscription = $this->subscriptionFor($user);

        return view('plans::admin.users.show', [
            'user' => $user,
            'currentPlan' => $user->currentPlan(),
            'planStatus' => $user->planStatus(),
            'billsThroughStripe' => Plans::billsThroughStripe(),
            'subscription' => $subscription,
            'subscriptionPlan' => Plan::forStripePrice($subscription?->stripe_price),
            'grantablePlans' => Plan::query()->where('is_default_free', false)->ordered()->get(),
            'auditLog' => PlanAdminAction::query()
                ->with('admin')
                ->where('target_user_id', $user->getKey())
                ->latest()
                ->limit(self::AUDIT_LOG_LIMIT)
                ->get(),
        ]);
    }

    public function updateTrial(UpdateTrialRequest $request, string $user): RedirectResponse
    {
        $user = $this->findUser($user);
        $trialEndsAt = Carbon::parse($request->validated('trial_ends_at'))->endOfDay();
        $previousTrialEndsAt = $user->trial_ends_at?->toDateString();

        $user->forceFill(['trial_ends_at' => $trialEndsAt])->save();

        PlanAdminAction::record($request->user(), $user, 'trial_updated', [
            'from' => $previousTrialEndsAt,
            'to' => $trialEndsAt->toDateString(),
        ]);

        return back()->with('success', "Trial now ends on {$trialEndsAt->toFormattedDateString()}.");
    }

    public function endTrial(Request $request, string $user): RedirectResponse
    {
        $user = $this->findUser($user);
        $previousTrialEndsAt = $user->trial_ends_at?->toDateString();

        $user->forceFill(['trial_ends_at' => now()])->save();

        PlanAdminAction::record($request->user(), $user, 'trial_ended', ['from' => $previousTrialEndsAt]);

        return back()->with('success', 'Trial ended.');
    }

    public function grantPlan(GrantPlanRequest $request, string $user): RedirectResponse
    {
        $user = $this->findUser($user);
        $plan = Plan::query()->findOrFail($request->validated('plan_id'));
        $grantedUntil = $request->validated('granted_until') ? Carbon::parse($request->validated('granted_until'))->endOfDay() : null;

        $user->forceFill(['granted_plan_id' => $plan->id, 'granted_until' => $grantedUntil])->save();

        PlanAdminAction::record($request->user(), $user, 'plan_granted', [
            'plan_id' => $plan->id,
            'plan' => $plan->name,
            'until' => $grantedUntil?->toDateString(),
        ]);

        return back()->with('success', $grantedUntil
            ? "{$plan->name} granted until {$grantedUntil->toFormattedDateString()}."
            : "{$plan->name} granted with no end date.");
    }

    public function revokePlan(Request $request, string $user): RedirectResponse
    {
        $user = $this->findUser($user);
        $previousPlanName = $user->grantedPlan?->name;

        $user->forceFill(['granted_plan_id' => null, 'granted_until' => null])->save();

        PlanAdminAction::record($request->user(), $user, 'plan_revoked', ['plan' => $previousPlanName]);

        return back()->with('success', 'Complimentary plan revoked.');
    }

    /**
     * Cancel at the end of the paid period, or immediately when `immediately` is set.
     */
    public function cancelSubscription(Request $request, string $user): RedirectResponse
    {
        $user = $this->findUser($user);
        $subscription = $this->subscriptionFor($user);

        abort_if($subscription === null || ! $subscription->valid() || $subscription->ended(), 404);

        $immediately = $request->boolean('immediately');

        if (! $immediately && $subscription->onGracePeriod()) {
            return back()->with('error', 'This subscription is already set to cancel at the end of the billing period.');
        }

        if ($immediately) {
            $this->stripeBilling->cancelNow($subscription);
        } else {
            $this->stripeBilling->cancel($subscription);
        }

        PlanAdminAction::record($request->user(), $user, $immediately ? 'subscription_cancelled_now' : 'subscription_cancelled', [
            'stripe_subscription_id' => $subscription->stripe_id,
        ]);

        return back()->with('success', $immediately
            ? 'Subscription cancelled immediately.'
            : 'Subscription will cancel at the end of the billing period.');
    }

    public function resumeSubscription(Request $request, string $user): RedirectResponse
    {
        $user = $this->findUser($user);
        $subscription = $this->subscriptionFor($user);

        if ($subscription === null || ! $subscription->onGracePeriod()) {
            return back()->with('error', 'Only a cancelled subscription that has not ended yet can be resumed.');
        }

        $this->stripeBilling->resume($subscription);

        PlanAdminAction::record($request->user(), $user, 'subscription_resumed', [
            'stripe_subscription_id' => $subscription->stripe_id,
        ]);

        return back()->with('success', 'Subscription resumed.');
    }

    public function toggleSuperAdmin(Request $request, string $user): RedirectResponse
    {
        $user = $this->findUser($user);

        if ($user->is($request->user())) {
            return back()->with('error', "You can't change your own super admin access.");
        }

        $user->forceFill(['is_super_admin' => ! $user->is_super_admin])->save();

        PlanAdminAction::record($request->user(), $user, $user->is_super_admin ? 'super_admin_granted' : 'super_admin_revoked');

        return back()->with('success', $user->is_super_admin ? "{$user->name} is now a super admin." : "{$user->name} is no longer a super admin.");
    }

    private function findUser(string $userId): Model
    {
        return Plans::userQuery()->findOrFail($userId);
    }

    private function subscriptionFor(Model $user): ?Subscription
    {
        return Plans::billsThroughStripe() ? $user->subscription(Plans::subscriptionType()) : null;
    }
}
