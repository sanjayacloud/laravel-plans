<?php

namespace Sanjayacloud\Plans;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Billable;

class Plans
{
    /**
     * @return class-string<Model>
     */
    public static function userModel(): string
    {
        return config('plans.user_model');
    }

    /**
     * @return Builder<Model>
     */
    public static function userQuery(): Builder
    {
        return static::userModel()::query();
    }

    /**
     * Features a plan can unlock, keyed by name, with their labels.
     *
     * @return array<string, string>
     */
    public static function features(): array
    {
        return config('plans.features', []);
    }

    /**
     * Usage limits a plan can set, keyed by name, with their labels.
     *
     * @return array<string, string>
     */
    public static function limits(): array
    {
        return config('plans.limits', []);
    }

    public static function featureLabel(string $feature): string
    {
        return static::features()[$feature] ?? str($feature)->replace('_', ' ')->ucfirst()->toString();
    }

    public static function subscriptionType(): string
    {
        return config('plans.subscription_type', 'default');
    }

    public static function adminGate(): string
    {
        return config('plans.admin.gate', 'manage-plans');
    }

    /**
     * Stripe billing is available when Cashier is installed and the user model is Billable.
     */
    public static function billsThroughStripe(): bool
    {
        return trait_exists(Billable::class)
            && class_exists(static::userModel())
            && in_array(Billable::class, class_uses_recursive(static::userModel()), true);
    }

    public static function upgradeMessage(string $feature): string
    {
        return static::featureLabel($feature)." isn't included in your plan. Upgrade to unlock it.";
    }

    public static function formatCents(int $cents): string
    {
        return config('plans.currency_symbol', '$').number_format($cents / 100, 2);
    }
}
