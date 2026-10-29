<?php

namespace Sanjayacloud\Plans;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Subscription;

/**
 * Every call the package makes to Stripe goes through here, so tests can swap it for a mock.
 */
class StripeBilling
{
    /**
     * Start a Stripe Checkout session for the subscription and return its URL.
     */
    public function checkoutUrl(Model $user, string $stripePriceId, string $returnUrl): string
    {
        return $user->newSubscription(Plans::subscriptionType(), $stripePriceId)
            ->checkout([
                'success_url' => $returnUrl.'?checkout=success',
                'cancel_url' => $returnUrl.'?checkout=cancelled',
            ])
            ->url;
    }

    public function billingPortalUrl(Model $user, string $returnUrl): string
    {
        return $user->billingPortalUrl($returnUrl);
    }

    /**
     * Cancel at the end of the current billing period.
     */
    public function cancel(Subscription $subscription): void
    {
        $subscription->cancel();
    }

    public function cancelNow(Subscription $subscription): void
    {
        $subscription->cancelNow();
    }

    public function resume(Subscription $subscription): void
    {
        $subscription->resume();
    }
}
