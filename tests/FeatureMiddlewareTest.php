<?php

use Illuminate\Support\Facades\Route;
use Sanjayacloud\Plans\Tests\Fixtures\User;

beforeEach(function () {
    createStandardPlans();

    Route::middleware(['web', 'auth', 'plan.feature:exports'])->get('/exports', fn () => 'exported');
    Route::middleware(['api', 'auth', 'plan.feature:exports'])->get('/api/exports', fn () => 'exported');
});

it('lets users whose plan has the feature through', function () {
    $this->actingAs(User::factory()->onTrial()->create())
        ->get('/exports')
        ->assertOk()
        ->assertSee('exported');
});

it('sends browsers without the feature to the upgrade page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/exports')
        ->assertRedirect(route('plans.billing.index'))
        ->assertSessionHas('error', "CSV exports isn't included in your plan. Upgrade to unlock it.");
});

it('answers JSON and API requests with 402 and the missing feature', function (string $url, array $headers) {
    $this->actingAs(User::factory()->create())
        ->get($url, $headers)
        ->assertPaymentRequired()
        ->assertExactJson([
            'message' => "CSV exports isn't included in your plan. Upgrade to unlock it.",
            'feature' => 'exports',
        ]);
})->with([
    'json request' => ['/exports', ['Accept' => 'application/json']],
    'api route' => ['/api/exports', []],
]);

it('answers 402 when there is no upgrade route', function () {
    config(['plans.upgrade_route' => null]);

    $this->actingAs(User::factory()->create())
        ->get('/exports')
        ->assertPaymentRequired();
});

it('labels unconfigured features from their key', function () {
    Route::middleware(['web', 'auth', 'plan.feature:team_seats'])->get('/seats', fn () => 'seats');

    $this->actingAs(User::factory()->onTrial()->create())
        ->get('/seats')
        ->assertSessionHas('error', "Team seats isn't included in your plan. Upgrade to unlock it.");
});
