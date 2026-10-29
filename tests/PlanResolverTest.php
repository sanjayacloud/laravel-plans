<?php

use Laravel\Cashier\Subscription;
use Sanjayacloud\Plans\Enums\PlanStatus;
use Sanjayacloud\Plans\Models\Plan;
use Sanjayacloud\Plans\Tests\Fixtures\User;
use Sanjayacloud\Plans\Tests\Fixtures\UserWithoutBilling;

beforeEach(function () {
    ['free' => $this->free, 'pro' => $this->pro] = createStandardPlans();
});

it('puts a user with no trial, grant or subscription on the default free plan', function () {
    $user = User::factory()->create(['trial_ends_at' => now()->subDay()]);

    expect($user->currentPlan()->is($this->free))->toBeTrue()
        ->and($user->planStatus())->toBe(PlanStatus::Free)
        ->and($user->hasFeature('exports'))->toBeFalse()
        ->and($user->planLimit('projects'))->toBe(2);
});

it('gives a user on the no-card trial the trial plan', function () {
    $user = User::factory()->onTrial(3)->create();

    expect($user->currentPlan()->is($this->pro))->toBeTrue()
        ->and($user->planStatus())->toBe(PlanStatus::Trial)
        ->and($user->onPlanTrial())->toBeTrue()
        ->and($user->hasFeature('exports'))->toBeTrue()
        ->and($user->planLimit('projects'))->toBeNull();
});

it('uses the plan matching an active subscription price', function (string $interval) {
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['features' => ['api_access']]);
    Subscription::factory()->for($user)->withPrice($plan->stripePriceId($interval))->create();

    expect($user->currentPlan()->is($plan))->toBeTrue()
        ->and($user->planStatus())->toBe(PlanStatus::Subscribed)
        ->and($user->hasFeature('api_access'))->toBeTrue()
        ->and($user->hasFeature('exports'))->toBeFalse();
})->with([Plan::INTERVAL_MONTHLY, Plan::INTERVAL_YEARLY]);

it('keeps the subscribed plan during the cancellation grace period', function () {
    $user = User::factory()->create();
    Subscription::factory()->for($user)->withPrice('price_promonthly')->create(['ends_at' => now()->addWeek()]);

    expect($user->currentPlan()->is($this->pro))->toBeTrue()
        ->and($user->planStatus())->toBe(PlanStatus::GracePeriod);
});

it('ignores ended and incomplete subscriptions', function (string $state) {
    $user = User::factory()->create();
    Subscription::factory()->for($user)->withPrice('price_promonthly')->{$state}()->create();

    expect($user->planStatus())->toBe(PlanStatus::Free);
})->with(['canceled', 'incomplete']);

it('falls back to the trial plan when a valid subscription price matches no plan', function () {
    $user = User::factory()->create();
    Subscription::factory()->for($user)->withPrice('price_retired')->create();

    expect($user->currentPlan()->is($this->pro))->toBeTrue()
        ->and($user->planStatus())->toBe(PlanStatus::Subscribed);
});

it('only looks at the configured subscription type', function () {
    $user = User::factory()->create();
    Subscription::factory()->for($user)->withPrice('price_promonthly')->create(['type' => 'addon']);

    expect($user->planStatus())->toBe(PlanStatus::Free);

    config(['plans.subscription_type' => 'addon']);

    expect($user->forgetResolvedPlan()->planStatus())->toBe(PlanStatus::Subscribed);
});

it('prefers a subscription over a grant and a trial', function () {
    $plan = Plan::factory()->create();
    $user = User::factory()->onTrial()->create(['granted_plan_id' => $this->pro->id]);
    Subscription::factory()->for($user)->withPrice($plan->stripe_monthly_price_id)->create();

    expect($user->currentPlan()->is($plan))->toBeTrue();
});

it('applies an unexpired admin grant before the trial', function (?string $grantedUntil) {
    $plan = Plan::factory()->create();
    $user = User::factory()->onTrial()->create([
        'granted_plan_id' => $plan->id,
        'granted_until' => $grantedUntil === null ? null : now()->modify($grantedUntil),
    ]);

    expect($user->currentPlan()->is($plan))->toBeTrue()
        ->and($user->planStatus())->toBe(PlanStatus::Complimentary);
})->with(['forever' => null, 'until next month' => '+1 month']);

it('ignores an expired admin grant', function () {
    $user = User::factory()->create([
        'granted_plan_id' => $this->pro->id,
        'granted_until' => now()->subDay(),
    ]);

    expect($user->planStatus())->toBe(PlanStatus::Free);
});

it('has no plan, features or limits when no plans exist', function () {
    Plan::query()->delete();
    $user = User::factory()->create();

    expect($user->currentPlan())->toBeNull()
        ->and($user->hasFeature('exports'))->toBeFalse()
        ->and($user->planLimit('projects'))->toBeNull();
});

it('caches the resolved plan until it is forgotten', function () {
    $user = User::factory()->create();

    expect($user->planStatus())->toBe(PlanStatus::Free);

    $user->forceFill(['trial_ends_at' => now()->addDay()])->save();

    expect($user->planStatus())->toBe(PlanStatus::Free)
        ->and($user->forgetResolvedPlan()->planStatus())->toBe(PlanStatus::Trial);
});

it('works for user models that are not Billable', function () {
    config(['plans.user_model' => UserWithoutBilling::class]);

    $grantedUser = UserWithoutBilling::factory()->create(['granted_plan_id' => $this->pro->id]);
    $trialUser = UserWithoutBilling::factory()->onTrial()->create();
    $freeUser = UserWithoutBilling::factory()->create();

    expect($grantedUser->planStatus())->toBe(PlanStatus::Complimentary)
        ->and($trialUser->planStatus())->toBe(PlanStatus::Trial)
        ->and($freeUser->planStatus())->toBe(PlanStatus::Free)
        ->and($this->pro->isPurchasable())->toBeFalse()
        ->and(UserWithoutBilling::query()->wherePlanStatus(PlanStatus::Trial)->pluck('id')->all())->toBe([$trialUser->id])
        ->and(UserWithoutBilling::query()->wherePlanStatus(PlanStatus::Subscribed)->count())->toBe(0);
});

describe('wherePlanStatus', function () {
    it('matches the resolver for every status', function () {
        $users = [
            'subscribed' => User::factory()->create(),
            'grace_period' => User::factory()->create(),
            'complimentary' => User::factory()->onTrial()->create(['granted_plan_id' => $this->pro->id]),
            'trial' => User::factory()->onTrial()->create(),
            'free' => User::factory()->create(['trial_ends_at' => now()->subDay()]),
        ];
        Subscription::factory()->for($users['subscribed'])->withPrice('price_promonthly')->create();
        Subscription::factory()->for($users['grace_period'])->withPrice('price_promonthly')->create(['ends_at' => now()->addWeek()]);

        foreach ($users as $status => $user) {
            expect($user->planStatus()->value)->toBe($status)
                ->and(User::query()->wherePlanStatus($status)->pluck('id')->all())->toBe([$user->id]);
        }
    });
});
