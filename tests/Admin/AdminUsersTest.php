<?php

use Laravel\Cashier\Subscription;
use Sanjayacloud\Plans\Enums\PlanStatus;
use Sanjayacloud\Plans\Models\PlanAdminAction;
use Sanjayacloud\Plans\StripeBilling;
use Sanjayacloud\Plans\Tests\Fixtures\User;

beforeEach(function () {
    $this->travelTo('2026-10-28 12:00');
    ['free' => $this->free, 'pro' => $this->pro] = createStandardPlans();
    $this->admin = User::factory()->superAdmin()->create();
});

function auditEntry(User $target, string $action): ?PlanAdminAction
{
    return PlanAdminAction::query()->where('target_user_id', $target->id)->where('action', $action)->first();
}

describe('users list', function () {
    it('lists users with their plan and status, escaping names', function () {
        $user = User::factory()->create(['name' => '<b>Mallory</b>']);

        $this->actingAs($this->admin)->get(route('plans.admin.users.index', ['search' => 'Mallory']))
            ->assertOk()
            ->assertSee('&lt;b&gt;Mallory&lt;/b&gt;', false)
            ->assertSee('data-user-row="'.$user->id.'"', false)
            ->assertDontSee('data-user-row="'.$this->admin->id.'"', false)
            ->assertSee('data-user-status="free"', false);
    });

    it('filters users by plan status', function () {
        $trialUser = User::factory()->onTrial()->create();
        $grantedUser = User::factory()->create(['granted_plan_id' => $this->pro->id]);

        $this->actingAs($this->admin)->get(route('plans.admin.users.index', ['status' => 'trial']))
            ->assertOk()
            ->assertSee('<option value="trial" selected>', false)
            ->assertSee('data-user-row="'.$trialUser->id.'"', false)
            ->assertDontSee('data-user-row="'.$grantedUser->id.'"', false);
    });

    it('rejects an unknown status filter', function () {
        $this->actingAs($this->admin)
            ->getJson(route('plans.admin.users.index', ['status' => 'vip']))
            ->assertUnprocessable();
    });

    it('paginates', function () {
        config(['plans.admin.per_page' => 2]);
        User::factory()->count(3)->create();

        $this->actingAs($this->admin)->get(route('plans.admin.users.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('data-user-row="'.$this->admin->id.'"', false);
    });
});

describe('user detail', function () {
    it('shows the user\'s plan and audit log', function () {
        $user = User::factory()->onTrial()->create();
        PlanAdminAction::record($this->admin, $user, 'trial_updated', ['to' => '2026-12-01']);

        $this->actingAs($this->admin)->get(route('plans.admin.users.show', $user->id))
            ->assertOk()
            ->assertSee('data-plan-status="trial"', false)
            ->assertSee('data-audit-action="trial_updated"', false);
    });

    it('returns 404 for an unknown user', function () {
        $this->actingAs($this->admin)->get(route('plans.admin.users.show', 999))->assertNotFound();
    });
});

describe('trial', function () {
    it('extends the trial to the end of the chosen day', function () {
        $user = User::factory()->onTrial(2)->create();

        $this->actingAs($this->admin)->put(route('plans.admin.users.trial.update', $user->id), ['trial_ends_at' => '2026-12-01'])
            ->assertSessionHas('success');

        expect($user->fresh()->trial_ends_at->toDateTimeString())->toBe('2026-12-01 23:59:59')
            ->and(auditEntry($user, 'trial_updated')->details)->toBe(['from' => '2026-10-30', 'to' => '2026-12-01']);
    });

    it('rejects a trial end date that is not in the future', function () {
        $this->actingAs($this->admin)->put(route('plans.admin.users.trial.update', User::factory()->create()->id), ['trial_ends_at' => '2026-10-28'])
            ->assertSessionHasErrors(['trial_ends_at' => 'The trial must end after today. Use "End trial now" to stop it.']);
    });

    it('ends the trial now', function () {
        $user = User::factory()->onTrial()->create();

        $this->actingAs($this->admin)->delete(route('plans.admin.users.trial.end', $user->id));

        expect($user->fresh()->planStatus())->toBe(PlanStatus::Free)
            ->and(auditEntry($user, 'trial_ended'))->not->toBeNull();
    });
});

describe('complimentary plan', function () {
    it('grants a plan until a date', function () {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->put(route('plans.admin.users.grant', $user->id), ['plan_id' => $this->pro->id, 'granted_until' => '2027-01-31']);

        $user->refresh();
        expect($user->granted_plan_id)->toBe($this->pro->id)
            ->and($user->granted_until->toDateString())->toBe('2027-01-31')
            ->and($user->planStatus())->toBe(PlanStatus::Complimentary)
            ->and(auditEntry($user, 'plan_granted')->details)->toBe(['plan_id' => $this->pro->id, 'plan' => 'Pro', 'until' => '2027-01-31']);
    });

    it('grants a plan with no end date', function () {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->put(route('plans.admin.users.grant', $user->id), ['plan_id' => $this->pro->id]);

        expect($user->fresh()->granted_until)->toBeNull();
    });

    it('does not grant the free plan', function () {
        $this->actingAs($this->admin)->put(route('plans.admin.users.grant', User::factory()->create()->id), ['plan_id' => $this->free->id])
            ->assertSessionHasErrors(['plan_id' => 'Choose a paid plan to grant.']);
    });

    it('revokes the grant', function () {
        $user = User::factory()->create(['granted_plan_id' => $this->pro->id, 'granted_until' => now()->addMonth()]);

        $this->actingAs($this->admin)->delete(route('plans.admin.users.grant.revoke', $user->id));

        expect($user->fresh())
            ->granted_plan_id->toBeNull()
            ->granted_until->toBeNull()
            ->and(auditEntry($user, 'plan_revoked')->details)->toBe(['plan' => 'Pro']);
    });
});

describe('stripe subscription', function () {
    it('cancels at the end of the billing period', function () {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->for($user)->create(['stripe_id' => 'sub_abc']);
        $this->mock(StripeBilling::class)
            ->shouldReceive('cancel')
            ->once()
            ->withArgs(fn (Subscription $cancelled) => $cancelled->is($subscription));

        $this->actingAs($this->admin)->post(route('plans.admin.users.subscription.cancel', $user->id))
            ->assertSessionHas('success', 'Subscription will cancel at the end of the billing period.');

        expect(auditEntry($user, 'subscription_cancelled')->details)->toBe(['stripe_subscription_id' => 'sub_abc']);
    });

    it('cancels immediately', function () {
        $user = User::factory()->create();
        Subscription::factory()->for($user)->create();
        $this->mock(StripeBilling::class)->shouldReceive('cancelNow')->once();

        $this->actingAs($this->admin)->post(route('plans.admin.users.subscription.cancel', $user->id), ['immediately' => '1']);

        expect(auditEntry($user, 'subscription_cancelled_now'))->not->toBeNull();
    });

    it('returns 404 when there is no subscription to cancel', function () {
        $this->mock(StripeBilling::class)->shouldNotReceive('cancel');

        $this->actingAs($this->admin)->post(route('plans.admin.users.subscription.cancel', User::factory()->create()->id))
            ->assertNotFound();
    });

    it('resumes a subscription in its grace period', function () {
        $user = User::factory()->create();
        Subscription::factory()->for($user)->create(['ends_at' => now()->addWeek()]);
        $this->mock(StripeBilling::class)->shouldReceive('resume')->once();

        $this->actingAs($this->admin)->post(route('plans.admin.users.subscription.resume', $user->id))
            ->assertSessionHas('success', 'Subscription resumed.');

        expect(auditEntry($user, 'subscription_resumed'))->not->toBeNull();
    });

    it('does not resume a subscription that is not cancelling', function () {
        $user = User::factory()->create();
        Subscription::factory()->for($user)->create();
        $this->mock(StripeBilling::class)->shouldNotReceive('resume');

        $this->actingAs($this->admin)->post(route('plans.admin.users.subscription.resume', $user->id))
            ->assertSessionHas('error');
    });
});

describe('super admin access', function () {
    it('grants and removes super admin access', function () {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->post(route('plans.admin.users.super-admin', $user->id));
        expect($user->fresh()->is_super_admin)->toBeTrue()
            ->and(auditEntry($user, 'super_admin_granted'))->not->toBeNull();

        $this->actingAs($this->admin)->post(route('plans.admin.users.super-admin', $user->id));
        expect($user->fresh()->is_super_admin)->toBeFalse()
            ->and(auditEntry($user, 'super_admin_revoked'))->not->toBeNull();
    });

    it('does not let admins change their own access', function () {
        $this->actingAs($this->admin)->post(route('plans.admin.users.super-admin', $this->admin->id))
            ->assertSessionHas('error');

        expect($this->admin->fresh()->is_super_admin)->toBeTrue();
    });
});
