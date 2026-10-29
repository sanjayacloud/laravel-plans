<?php

namespace Sanjayacloud\Plans\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Subscription;
use Sanjayacloud\Plans\Database\Factories\PlanFactory;
use Sanjayacloud\Plans\Plans;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $monthly_price
 * @property int $yearly_price
 * @property string|null $stripe_monthly_price_id
 * @property string|null $stripe_yearly_price_id
 * @property array<int, string> $features
 * @property array<string, int|null> $limits
 * @property int $trial_days
 * @property bool $is_default_free
 * @property bool $is_trial_plan
 * @property bool $is_active
 * @property int $sort_order
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    public const INTERVAL_MONTHLY = 'monthly';

    public const INTERVAL_YEARLY = 'yearly';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'monthly_price',
        'yearly_price',
        'stripe_monthly_price_id',
        'stripe_yearly_price_id',
        'features',
        'limits',
        'trial_days',
        'is_default_free',
        'is_trial_plan',
        'is_active',
        'sort_order',
    ];

    protected $attributes = [
        'features' => '[]',
        'limits' => '[]',
    ];

    public function getTable(): string
    {
        return config('plans.tables.plans', parent::getTable());
    }

    protected function casts(): array
    {
        return [
            'monthly_price' => 'integer',
            'yearly_price' => 'integer',
            'features' => 'array',
            'limits' => 'array',
            'trial_days' => 'integer',
            'is_default_free' => 'boolean',
            'is_trial_plan' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function newFactory(): PlanFactory
    {
        return PlanFactory::new();
    }

    public function grantedUsers(): HasMany
    {
        return $this->hasMany(Plans::userModel(), 'granted_plan_id');
    }

    /**
     * @param  Builder<Plan>  $query
     * @return Builder<Plan>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Plan>  $query
     * @return Builder<Plan>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public static function defaultFree(): ?self
    {
        return self::query()->where('is_default_free', true)->first();
    }

    public static function trialPlan(): ?self
    {
        return self::query()->where('is_trial_plan', true)->first();
    }

    public static function forStripePrice(?string $stripePriceId): ?self
    {
        if ($stripePriceId === null) {
            return null;
        }

        return self::query()
            ->where('stripe_monthly_price_id', $stripePriceId)
            ->orWhere('stripe_yearly_price_id', $stripePriceId)
            ->first();
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }

    /**
     * The plan's limit for the key, or null when it is unlimited.
     */
    public function limitFor(string $key): ?int
    {
        $value = $this->limits[$key] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function stripePriceId(string $interval): ?string
    {
        return $interval === self::INTERVAL_YEARLY ? $this->stripe_yearly_price_id : $this->stripe_monthly_price_id;
    }

    public function isPurchasable(): bool
    {
        return $this->is_active
            && Plans::billsThroughStripe()
            && ($this->stripe_monthly_price_id !== null || $this->stripe_yearly_price_id !== null);
    }

    /**
     * Cashier subscriptions (active or not) on one of this plan's Stripe prices.
     *
     * @return Builder<Subscription>|null
     */
    public function subscriptionsQuery(): ?Builder
    {
        if (! class_exists(Subscription::class)) {
            return null;
        }

        $priceIds = array_values(array_filter([$this->stripe_monthly_price_id, $this->stripe_yearly_price_id]));

        return Subscription::query()->whereIn('stripe_price', $priceIds === [] ? [''] : $priceIds);
    }

    public static function formatCents(int $cents): string
    {
        return Plans::formatCents($cents);
    }
}
