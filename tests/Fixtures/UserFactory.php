<?php

namespace Sanjayacloud\Plans\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'is_super_admin' => false,
            'trial_ends_at' => null,
        ];
    }

    public function onTrial(int $days = 14): static
    {
        return $this->state(fn (array $attributes) => ['trial_ends_at' => now()->addDays($days)]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => ['is_super_admin' => true]);
    }
}
