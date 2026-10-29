<?php

namespace Sanjayacloud\Plans;

use Illuminate\Database\Eloquent\Model;
use Sanjayacloud\Plans\Enums\PlanStatus;
use Sanjayacloud\Plans\Models\Plan;

class PlanResolver
{
    /**
     * Work out which plan applies, in priority order: a valid Stripe subscription,
     * an admin-granted plan that hasn't expired, the generic trial, then the default free plan.
     * A subscription on a price no plan knows about falls back to the trial plan so paying users keep access.
     *
     * @return array{plan: ?Plan, status: PlanStatus}
     */
    public function resolve(Model $user): array
    {
        $subscription = method_exists($user, 'subscription') ? $user->subscription(Plans::subscriptionType()) : null;

        if ($subscription !== null && $subscription->valid()) {
            return [
                'plan' => Plan::forStripePrice($subscription->stripe_price) ?? Plan::trialPlan(),
                'status' => $subscription->onGracePeriod() ? PlanStatus::GracePeriod : PlanStatus::Subscribed,
            ];
        }

        if ($user->granted_plan_id !== null && ($user->granted_until === null || $user->granted_until->isFuture())) {
            $grantedPlan = Plan::query()->find($user->granted_plan_id);

            if ($grantedPlan !== null) {
                return ['plan' => $grantedPlan, 'status' => PlanStatus::Complimentary];
            }
        }

        if ($user->trial_ends_at !== null && $user->trial_ends_at->isFuture()) {
            $trialPlan = Plan::trialPlan();

            if ($trialPlan !== null) {
                return ['plan' => $trialPlan, 'status' => PlanStatus::Trial];
            }
        }

        return ['plan' => Plan::defaultFree(), 'status' => PlanStatus::Free];
    }
}
