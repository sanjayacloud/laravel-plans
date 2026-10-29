<?php

namespace Sanjayacloud\Plans\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Laravel\Cashier\Subscription;
use Sanjayacloud\Plans\Enums\PlanStatus;
use Sanjayacloud\Plans\Models\Plan;
use Sanjayacloud\Plans\Plans;

class DashboardController extends Controller
{
    private const RECENT_LIMIT = 8;

    public function index(): View
    {
        $billsThroughStripe = Plans::billsThroughStripe();

        $statusCounts = collect(PlanStatus::cases())
            ->reject(fn (PlanStatus $status): bool => ! $billsThroughStripe && in_array($status, [PlanStatus::Subscribed, PlanStatus::GracePeriod], true))
            ->mapWithKeys(fn (PlanStatus $status): array => [$status->value => [
                'label' => $status->label(),
                'count' => Plans::userQuery()->wherePlanStatus($status)->count(),
            ]]);

        return view('plans::admin.dashboard', [
            'statusCounts' => $statusCounts,
            'totalUsers' => Plans::userQuery()->count(),
            'billsThroughStripe' => $billsThroughStripe,
            'monthlyRecurringRevenue' => $billsThroughStripe ? $this->estimatedMonthlyRecurringRevenue() : null,
            'recentSignUps' => Plans::userQuery()->latest()->limit(self::RECENT_LIMIT)->get(),
            'recentCancellations' => $billsThroughStripe ? Subscription::query()
                ->with('user')
                ->whereNotNull('ends_at')
                ->latest('updated_at')
                ->limit(self::RECENT_LIMIT)
                ->get() : new Collection,
        ]);
    }

    /**
     * Active, not-cancelling subscriptions times each plan's listed price, with yearly prices spread over 12 months.
     * Discounts, taxes and Stripe-side price changes aren't reflected.
     */
    private function estimatedMonthlyRecurringRevenue(): int
    {
        return (int) round(Plan::query()->get()->sum(function (Plan $plan): float {
            $countForPrice = fn (?string $stripePriceId): int => $stripePriceId === null ? 0 : Subscription::query()
                ->active()
                ->notOnTrial()
                ->notOnGracePeriod()
                ->where('stripe_price', $stripePriceId)
                ->count();

            return $countForPrice($plan->stripe_monthly_price_id) * $plan->monthly_price
                + $countForPrice($plan->stripe_yearly_price_id) * $plan->yearly_price / 12;
        }));
    }
}
