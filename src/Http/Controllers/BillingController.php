<?php

namespace Sanjayacloud\Plans\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Sanjayacloud\Plans\Enums\PlanStatus;
use Sanjayacloud\Plans\Http\Requests\StartCheckoutRequest;
use Sanjayacloud\Plans\Models\Plan;
use Sanjayacloud\Plans\Plans;
use Sanjayacloud\Plans\StripeBilling;

class BillingController extends Controller
{
    public function __construct(private StripeBilling $stripeBilling) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $subscription = Plans::billsThroughStripe() ? $user->subscription(Plans::subscriptionType()) : null;

        return view('plans::billing.index', [
            'plans' => Plan::query()->active()->ordered()->get(),
            'currentPlan' => $user->currentPlan(),
            'planStatus' => $user->planStatus(),
            'subscription' => $subscription !== null && $subscription->valid() ? $subscription : null,
            'trialEndsAt' => $user->planStatus() === PlanStatus::Trial ? $user->trial_ends_at : null,
            'billsThroughStripe' => Plans::billsThroughStripe(),
            'checkoutResult' => $request->query('checkout'),
        ]);
    }

    /**
     * Send the user to Stripe Checkout. Users who already subscribe change plans in the billing portal instead.
     */
    public function checkout(StartCheckoutRequest $request, Plan $plan): RedirectResponse
    {
        $stripePriceId = $plan->stripePriceId($request->validated('interval'));

        abort_if(! $plan->is_active || $stripePriceId === null, 404);

        $user = $request->user();

        if ($user->subscription(Plans::subscriptionType())?->valid()) {
            return redirect()->route('plans.billing.index')
                ->with('error', 'You already have a subscription. Use "Manage billing" to change plans.');
        }

        return redirect()->away($this->stripeBilling->checkoutUrl($user, $stripePriceId, route('plans.billing.index')));
    }

    public function portal(Request $request): RedirectResponse
    {
        if (! $request->user()->hasStripeId()) {
            return redirect()->route('plans.billing.index')
                ->with('error', "You don't have any billing history yet. Choose a plan to get started.");
        }

        return redirect()->away($this->stripeBilling->billingPortalUrl($request->user(), route('plans.billing.index')));
    }
}
