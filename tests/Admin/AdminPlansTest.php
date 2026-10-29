<?php

use Laravel\Cashier\Subscription;
use Sanjayacloud\Plans\Models\Plan;
use Sanjayacloud\Plans\Models\PlanAdminAction;
use Sanjayacloud\Plans\Tests\Fixtures\User;

beforeEach(function () {
    ['free' => $this->free, 'pro' => $this->pro] = createStandardPlans();
    $this->admin = User::factory()->superAdmin()->create();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function planInput(array $overrides = []): array
{
    return [
        'name' => 'Team',
        'description' => 'For teams.',
        'monthly_price' => '9.99',
        'yearly_price' => '99',
        'stripe_monthly_price_id' => 'price_teammonthly',
        'stripe_yearly_price_id' => 'price_teamyearly',
        'features' => ['exports'],
        'limits' => ['projects' => ''],
        'trial_days' => 7,
        'sort_order' => 2,
        'is_active' => '1',
        ...$overrides,
    ];
}

it('lists the plans', function () {
    $this->actingAs($this->admin)->get(route('plans.admin.plans.index'))
        ->assertOk()
        ->assertSee('data-plan-row="free"', false)
        ->assertSee('data-plan-row="pro"', false);
});

it('shows the plan forms with prices in dollars and the configured features and limits', function () {
    $this->actingAs($this->admin)->get(route('plans.admin.plans.create'))
        ->assertOk()
        ->assertSee('CSV exports')
        ->assertSee('name="limits[projects]"', false);

    $this->actingAs($this->admin)->get(route('plans.admin.plans.edit', $this->pro))
        ->assertOk()
        ->assertSee('value="4.99"', false)
        ->assertSee('value="49.00"', false);
});

it('creates a plan with prices in cents and blank limits as unlimited', function () {
    $this->actingAs($this->admin)->post(route('plans.admin.plans.store'), planInput([
        'limits' => ['projects' => '10', 'not_configured' => '5'],
    ]))->assertRedirect(route('plans.admin.plans.index'));

    $plan = Plan::query()->where('slug', 'team')->firstOrFail();

    expect($plan->monthly_price)->toBe(999)
        ->and($plan->yearly_price)->toBe(9900)
        ->and($plan->features)->toBe(['exports'])
        ->and($plan->limits)->toBe(['projects' => 10])
        ->and($plan->is_active)->toBeTrue()
        ->and(PlanAdminAction::query()->where('action', 'plan_created')->where('admin_id', $this->admin->id)->exists())->toBeTrue();
});

it('updates a plan', function () {
    $plan = Plan::factory()->create();

    $this->actingAs($this->admin)->put(route('plans.admin.plans.update', $plan), planInput(['name' => 'Studio']))
        ->assertRedirect(route('plans.admin.plans.index'));

    expect($plan->fresh())
        ->name->toBe('Studio')
        ->slug->toBe('studio')
        ->stripe_monthly_price_id->toBe('price_teammonthly')
        ->and(PlanAdminAction::query()->where('action', 'plan_updated')->exists())->toBeTrue();
});

it('moves the trial and default free flags so only one plan has each', function () {
    $this->actingAs($this->admin)->post(route('plans.admin.plans.store'), planInput(['is_trial_plan' => '1']));

    expect(Plan::query()->where('is_trial_plan', true)->pluck('slug')->all())->toBe(['team']);

    $this->actingAs($this->admin)->post(route('plans.admin.plans.store'), planInput([
        'name' => 'Basic',
        'stripe_monthly_price_id' => '',
        'stripe_yearly_price_id' => '',
        'is_default_free' => '1',
    ]));

    expect(Plan::query()->where('is_default_free', true)->pluck('slug')->all())->toBe(['basic']);
});

it('rejects invalid plan input', function (array $overrides, string $field, string $message) {
    Plan::factory()->create(['slug' => 'taken', 'stripe_monthly_price_id' => 'price_used']);

    $this->actingAs($this->admin)->post(route('plans.admin.plans.store'), planInput($overrides))
        ->assertSessionHasErrors([$field => $message]);
})->with([
    'price ID without the price_ prefix' => [['stripe_monthly_price_id' => 'prod_123'], 'stripe_monthly_price_id', 'Stripe price IDs start with "price_", for example price_1Abc23.'],
    'price ID used by another plan' => [['stripe_monthly_price_id' => 'price_used'], 'stripe_monthly_price_id', 'Another plan already uses this monthly price ID.'],
    'same monthly and yearly price' => [['stripe_yearly_price_id' => 'price_teammonthly'], 'stripe_monthly_price_id', 'The monthly and yearly price IDs must be different.'],
    'slug used by another plan' => [['slug' => 'taken'], 'slug', 'Another plan already uses this slug.'],
    'unknown feature' => [['features' => ['teleport']], 'features.0', 'One of the selected features does not exist.'],
    'zero limit' => [['limits' => ['projects' => '0']], 'limits.projects', 'Limits must be at least 1, or left blank for unlimited.'],
    'default free plan with prices' => [['is_default_free' => '1'], 'is_default_free', 'The default free plan cannot have Stripe prices.'],
    'inactive default free plan' => [['is_default_free' => '1', 'is_active' => '0', 'stripe_monthly_price_id' => '', 'stripe_yearly_price_id' => ''], 'is_active', 'The default free plan must stay active.'],
]);

it('keeps one default free plan', function () {
    $this->actingAs($this->admin)->put(route('plans.admin.plans.update', $this->free), planInput([
        'name' => 'Free',
        'stripe_monthly_price_id' => '',
        'stripe_yearly_price_id' => '',
    ]))->assertSessionHasErrors(['is_default_free' => 'There must always be one default free plan. Make another plan the default instead.']);

    expect($this->free->fresh()->is_default_free)->toBeTrue();
});

it('deletes an unused plan', function () {
    $plan = Plan::factory()->create();

    $this->actingAs($this->admin)->delete(route('plans.admin.plans.destroy', $plan))
        ->assertRedirect(route('plans.admin.plans.index'));

    $this->assertModelMissing($plan);
});

it('refuses to delete a plan that people use', function (Closure $attachUser) {
    $plan = Plan::factory()->create();
    $attachUser($plan);

    $this->actingAs($this->admin)->delete(route('plans.admin.plans.destroy', $plan))
        ->assertSessionHas('error');

    $this->assertModelExists($plan);
})->with([
    'active subscriber' => fn (Plan $plan) => Subscription::factory()->for(User::factory())->withPrice($plan->stripe_monthly_price_id)->create(),
    'complimentary user' => fn (Plan $plan) => User::factory()->create(['granted_plan_id' => $plan->id]),
]);

it('refuses to delete the default free plan', function () {
    $this->actingAs($this->admin)->delete(route('plans.admin.plans.destroy', $this->free))
        ->assertSessionHas('error');

    $this->assertModelExists($this->free);
});
