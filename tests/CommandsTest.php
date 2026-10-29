<?php

use Laravel\Cashier\Subscription;
use Sanjayacloud\Plans\Models\Plan;
use Sanjayacloud\Plans\Tests\Fixtures\User;

beforeEach(function () {
    ['pro' => $this->pro] = createStandardPlans();
});

describe('plans:grant-admin', function () {
    it('makes the user with that email a super admin', function () {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('plans:grant-admin', ['email' => 'owner@example.com'])
            ->expectsOutput("{$user->name} is now a super admin.")
            ->assertSuccessful();

        expect($user->fresh()->is_super_admin)->toBeTrue();
    });

    it('removes access with --revoke', function () {
        $user = User::factory()->superAdmin()->create(['email' => 'owner@example.com']);

        $this->artisan('plans:grant-admin', ['email' => 'owner@example.com', '--revoke' => true])
            ->expectsOutput("{$user->name} is no longer a super admin.")
            ->assertSuccessful();

        expect($user->fresh()->is_super_admin)->toBeFalse();
    });

    it('fails for an unknown email', function () {
        $this->artisan('plans:grant-admin', ['email' => 'nobody@example.com'])
            ->expectsOutput('No user has the email nobody@example.com.')
            ->assertFailed();
    });
});

describe('plans:start-trials', function () {
    beforeEach(function () {
        $this->travelTo('2026-10-28 12:00');
    });

    it('starts a trial only for users who never had a trial, grant or subscription', function () {
        $neverTrialled = User::factory()->create();
        $hadTrial = User::factory()->create(['trial_ends_at' => now()->subMonth()]);
        $granted = User::factory()->create(['granted_plan_id' => $this->pro->id]);
        $subscriber = User::factory()->create();
        Subscription::factory()->for($subscriber)->create();

        $this->artisan('plans:start-trials')
            ->expectsOutput('Started a 14-day trial for 1 users.')
            ->assertSuccessful();

        expect($neverTrialled->fresh()->trial_ends_at->toDateString())->toBe('2026-11-11')
            ->and($hadTrial->fresh()->trial_ends_at->toDateString())->toBe('2026-09-28')
            ->and($granted->fresh()->trial_ends_at)->toBeNull()
            ->and($subscriber->fresh()->trial_ends_at)->toBeNull();
    });

    it('uses --days over the trial plan\'s length', function () {
        $user = User::factory()->create();

        $this->artisan('plans:start-trials', ['--days' => 30])->assertSuccessful();

        expect($user->fresh()->trial_ends_at->toDateString())->toBe('2026-11-27');
    });

    it('fails when there is no trial length', function (Closure $setUp, array $options) {
        $setUp();

        $this->artisan('plans:start-trials', $options)->assertFailed();
    })->with([
        'zero days' => [fn () => null, ['--days' => 0]],
        'no trial plan' => [fn () => Plan::query()->update(['is_trial_plan' => false]), []],
    ]);
});
