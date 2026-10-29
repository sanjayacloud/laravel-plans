<?php

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Laravel\Cashier\Subscription;
use Sanjayacloud\Plans\Enums\PlanStatus;
use Sanjayacloud\Plans\Models\Plan;
use Sanjayacloud\Plans\StripeBilling;
use Sanjayacloud\Plans\Tests\Fixtures\User;

beforeEach(function () {
    ['free' => $this->free, 'pro' => $this->pro] = createStandardPlans();
});

describe('pricing page', function () {
    it('shows active plans with prices, features and the user\'s trial', function () {
        Plan::factory()->inactive()->create(['name' => 'Retired']);

        $this->actingAs(User::factory()->onTrial(5)->create())
            ->get(route('plans.billing.index'))
            ->assertOk()
            ->assertSee('Free trial')
            ->assertSee('$4.99')
            ->assertSee('$49.00')
            ->assertSee('CSV exports')
            ->assertSee('Projects: 2')
            ->assertSee('Your free trial ends on')
            ->assertSee(route('plans.billing.checkout', $this->pro))
            ->assertDontSee('Retired');
    });

    it('marks plans without Stripe prices as coming soon', function () {
        $this->pro->update(['stripe_monthly_price_id' => null, 'stripe_yearly_price_id' => null]);

        $this->actingAs(User::factory()->create())
            ->get(route('plans.billing.index'))
            ->assertSee('Coming soon')
            ->assertDontSee(route('plans.billing.checkout', $this->pro));
    });

    it('requires sign in', function () {
        $this->get(route('plans.billing.index'))->assertRedirect(route('login'));
    });

    it('uses the configured prefix', function () {
        expect(route('plans.billing.index', absolute: false))->toBe('/billing');
    });
});

describe('checkout', function () {
    it('sends the user to Stripe Checkout for the chosen interval', function (string $interval, string $stripePriceId) {
        $user = User::factory()->create();
        $this->mock(StripeBilling::class)
            ->shouldReceive('checkoutUrl')
            ->once()
            ->withArgs(fn (User $checkoutUser, string $priceId, string $returnUrl) => $checkoutUser->is($user)
                && $priceId === $stripePriceId
                && $returnUrl === route('plans.billing.index'))
            ->andReturn('https://checkout.stripe.com/c/pay/cs_test_123');

        $this->actingAs($user)->post(route('plans.billing.checkout', $this->pro), ['interval' => $interval])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_123');
    })->with([
        'monthly' => [Plan::INTERVAL_MONTHLY, 'price_promonthly'],
        'yearly' => [Plan::INTERVAL_YEARLY, 'price_proyearly'],
    ]);

    it('returns 404 for a plan that cannot be bought', function (Closure $makePlan) {
        $this->mock(StripeBilling::class)->shouldNotReceive('checkoutUrl');

        $this->actingAs(User::factory()->create())
            ->post(route('plans.billing.checkout', $makePlan()), ['interval' => Plan::INTERVAL_MONTHLY])
            ->assertNotFound();
    })->with([
        'free plan' => fn () => Plan::query()->where('slug', 'free')->first(),
        'inactive plan' => fn () => Plan::factory()->inactive()->create(),
        'no price for the interval' => fn () => Plan::factory()->create(['stripe_monthly_price_id' => null]),
    ]);

    it('requires a billing interval', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('plans.billing.checkout', $this->pro), ['interval' => 'weekly'])
            ->assertSessionHasErrors(['interval' => 'Choose monthly or yearly billing.']);
    });

    it('sends existing subscribers to the billing portal instead of a second subscription', function () {
        $user = User::factory()->create();
        Subscription::factory()->for($user)->withPrice('price_promonthly')->create();
        $this->mock(StripeBilling::class)->shouldNotReceive('checkoutUrl');

        $this->actingAs($user)->post(route('plans.billing.checkout', $this->pro), ['interval' => Plan::INTERVAL_YEARLY])
            ->assertRedirect(route('plans.billing.index'))
            ->assertSessionHas('error');
    });
});

describe('billing portal', function () {
    it('redirects Stripe customers to the portal', function () {
        $this->mock(StripeBilling::class)
            ->shouldReceive('billingPortalUrl')
            ->once()
            ->andReturn('https://billing.stripe.com/p/session/test_123');

        $this->actingAs(User::factory()->create(['stripe_id' => 'cus_123']))
            ->get(route('plans.billing.portal'))
            ->assertRedirect('https://billing.stripe.com/p/session/test_123');
    });

    it('explains there is nothing to manage before the first purchase', function () {
        $this->mock(StripeBilling::class)->shouldNotReceive('billingPortalUrl');

        $this->actingAs(User::factory()->create())->get(route('plans.billing.portal'))
            ->assertRedirect(route('plans.billing.index'))
            ->assertSessionHas('error');
    });
});

describe('stripe webhook', function () {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function subscriptionUpdatedPayload(array $overrides = []): array
    {
        return [
            'id' => 'evt_123',
            'type' => 'customer.subscription.updated',
            'data' => ['object' => [
                'id' => 'sub_123',
                'customer' => 'cus_123',
                'status' => 'active',
                'cancel_at_period_end' => false,
                'items' => ['data' => [[
                    'id' => 'si_123',
                    'price' => ['id' => 'price_promonthly', 'product' => 'prod_123'],
                    'quantity' => 1,
                ]]],
                ...$overrides,
            ]],
        ];
    }

    it('moves a user to the plan matching their new Stripe price', function () {
        $user = User::factory()->create(['stripe_id' => 'cus_123']);

        $this->postJson('/stripe/webhook', subscriptionUpdatedPayload())->assertOk();

        expect($user->refresh()->planStatus())->toBe(PlanStatus::Subscribed)
            ->and($user->currentPlan()->is($this->pro))->toBeTrue();
    });

    it('drops a user to the free plan when Stripe cancels the subscription', function () {
        $user = User::factory()->create(['stripe_id' => 'cus_123']);
        Subscription::factory()->for($user)->withPrice('price_promonthly')->create(['stripe_id' => 'sub_123']);

        $this->postJson('/stripe/webhook', subscriptionUpdatedPayload([
            'status' => 'canceled',
            'canceled_at' => now()->subMinute()->timestamp,
        ]))->assertOk();

        expect($user->refresh()->planStatus())->toBe(PlanStatus::Free);
    });
});

describe('trial on registration', function () {
    it('starts the trial plan\'s trial when the Registered event fires', function () {
        $user = User::factory()->create();

        event(new Registered($user));

        expect($user->trial_ends_at->isSameDay(now()->addDays(14)))->toBeTrue()
            ->and($user->planStatus())->toBe(PlanStatus::Trial);
    });

    it('leaves the user alone when it is switched off, there is no trial plan, or they already had a trial', function (Closure $setUp) {
        $user = User::factory()->create(['trial_ends_at' => null]);
        $setUp($user);
        $trialEndsAt = $user->refresh()->trial_ends_at;

        event(new Registered($user));

        expect($user->refresh()->trial_ends_at?->toIso8601String())->toBe($trialEndsAt?->toIso8601String());
    })->with([
        'switched off' => fn () => config(['plans.trial.start_on_registration' => false]),
        'no trial plan' => fn () => Plan::query()->update(['is_trial_plan' => false]),
        'already had a trial' => fn (User $user) => $user->forceFill(['trial_ends_at' => now()->subYear()])->save(),
    ]);
});

describe('banner', function () {
    beforeEach(function () {
        Route::middleware(['web', 'auth'])->get('/banner', fn () => Blade::render('<x-plans::banner />'));
    });

    it('shows the days left in a trial', function () {
        $this->actingAs(User::factory()->create(['trial_ends_at' => now()->addDays(3)->addHour()]))
            ->get('/banner')
            ->assertSee('Pro trial: 4 days left.');
    });

    it('tells users when their trial has ended', function () {
        $this->actingAs(User::factory()->create(['trial_ends_at' => now()->subDay()]))
            ->get('/banner')
            ->assertSee("Your trial ended. You're on the Free plan now.", false);
    });

    it('warns subscribers who cancelled', function () {
        $user = User::factory()->create();
        Subscription::factory()->for($user)->withPrice('price_promonthly')->create(['ends_at' => now()->addWeek()]);

        $this->actingAs($user)->get('/banner')->assertSee('data-plan-banner="grace-period"', false);
    });

    it('shows nothing to active subscribers', function () {
        $user = User::factory()->create();
        Subscription::factory()->for($user)->withPrice('price_promonthly')->create();

        $this->actingAs($user)->get('/banner')->assertDontSee('data-plan-banner', false);
    });
});
