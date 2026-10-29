<?php

use Sanjayacloud\Plans\Models\Plan;
use Sanjayacloud\Plans\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * The usual setup: a default free plan with a project limit and a paid plan new users trial.
 *
 * @return array{free: Plan, pro: Plan}
 */
function createStandardPlans(): array
{
    return [
        'free' => Plan::factory()->free(['projects' => 2])->create(['slug' => 'free']),
        'pro' => Plan::factory()->trial()->create([
            'name' => 'Pro',
            'slug' => 'pro',
            'stripe_monthly_price_id' => 'price_promonthly',
            'stripe_yearly_price_id' => 'price_proyearly',
        ]),
    ];
}
