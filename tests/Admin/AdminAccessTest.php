<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Sanjayacloud\Plans\Models\Plan;
use Sanjayacloud\Plans\Tests\Fixtures\User;

beforeEach(function () {
    createStandardPlans();
});

it('forbids non-admins from every admin route', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create();

    $adminRoutes = collect(Route::getRoutes()->getRoutesByName())
        ->filter(fn ($route, string $name) => str_starts_with($name, 'plans.admin.'));

    expect($adminRoutes)->not->toBeEmpty();

    foreach ($adminRoutes as $route) {
        $url = '/'.str_replace(['{plan}', '{user}'], [$plan->id, $user->id], $route->uri());

        $this->actingAs($user)->call($route->methods()[0], $url)->assertForbidden();
    }
});

it('sends guests to the login page', function () {
    $this->get(route('plans.admin.dashboard'))->assertRedirect(route('login'));
});

it('lets the app redefine who is an admin', function () {
    $user = User::factory()->create(['email' => 'owner@example.com']);
    Gate::define('manage-plans', fn (User $gateUser): bool => $gateUser->email === 'owner@example.com');

    $this->actingAs($user)->get(route('plans.admin.dashboard'))->assertOk();
});

it('shows super admins the overview with counts by plan status and estimated MRR', function () {
    $admin = User::factory()->superAdmin()->create();
    $monthlySubscriber = User::factory()->create();
    $monthlySubscriber->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_1', 'stripe_status' => 'active', 'stripe_price' => 'price_promonthly']);
    $yearlySubscriber = User::factory()->create();
    $yearlySubscriber->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_2', 'stripe_status' => 'active', 'stripe_price' => 'price_proyearly']);
    User::factory()->onTrial()->create();

    $this->actingAs($admin)->get(route('plans.admin.dashboard'))
        ->assertOk()
        ->assertSee('data-status-count="subscribed">2<', false)
        ->assertSee('data-status-count="trial">1<', false)
        ->assertSee('data-status-count="free">1<', false)
        ->assertSee('data-mrr>$9.07<', false);
});
