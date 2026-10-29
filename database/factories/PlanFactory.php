<?php

namespace Sanjayacloud\Plans\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Sanjayacloud\Plans\Models\Plan;
use Sanjayacloud\Plans\Plans;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * A paid plan with every configured feature and no limits.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->word());

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->sentence(),
            'monthly_price' => 499,
            'yearly_price' => 4900,
            'stripe_monthly_price_id' => 'price_'.Str::random(14),
            'stripe_yearly_price_id' => 'price_'.Str::random(14),
            'features' => array_keys(Plans::features()),
            'limits' => [],
            'trial_days' => 0,
            'is_default_free' => false,
            'is_trial_plan' => false,
            'is_active' => true,
            'sort_order' => 1,
        ];
    }

    /**
     * The default free plan: no features, no Stripe prices.
     *
     * @param  array<string, int|null>  $limits
     */
    public function free(array $limits = []): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Free',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'stripe_monthly_price_id' => null,
            'stripe_yearly_price_id' => null,
            'features' => [],
            'limits' => $limits,
            'is_default_free' => true,
            'sort_order' => 0,
        ]);
    }

    /**
     * The plan new users try for free.
     */
    public function trial(int $days = 14): static
    {
        return $this->state(fn (array $attributes) => [
            'trial_days' => $days,
            'is_trial_plan' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
