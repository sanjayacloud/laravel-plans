@extends(config('plans.layout', 'plans::layouts.default'))

@section('title', 'Plan & Billing')

@section(config('plans.layout_section', 'content'))
<div class="mx-auto flex max-w-5xl flex-col gap-6 px-4">
    @include('plans::partials.flash')

    @if($checkoutResult === 'success')
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
            Thanks for subscribing! Your plan updates as soon as Stripe confirms the payment, usually within a few seconds.
        </div>
    @elseif($checkoutResult === 'cancelled')
        <div class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200">
            Checkout was cancelled. You haven't been charged.
        </div>
    @endif

    <div class="flex flex-col gap-4 rounded-xl bg-white p-6 shadow sm:flex-row sm:items-center sm:justify-between dark:bg-gray-900">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold">Plan &amp; Billing</h1>
            <p class="text-gray-600 dark:text-gray-400">
                You're on <span class="font-semibold">{{ $currentPlan?->name ?? 'no plan' }}</span>
                <span class="ml-1 rounded-full border border-gray-300 px-2 py-0.5 text-xs dark:border-gray-700" data-plan-status="{{ $planStatus->value }}">{{ $planStatus->label() }}</span>
            </p>
            @if($trialEndsAt)
                <p class="text-sm text-gray-600 dark:text-gray-400">Your free trial ends on {{ $trialEndsAt->toFormattedDateString() }}. No card needed until you choose a plan.</p>
            @endif
            @if($subscription?->onGracePeriod())
                <p class="text-sm text-amber-600 dark:text-amber-400">Your subscription is cancelled and ends on {{ $subscription->ends_at->toFormattedDateString() }}.</p>
            @endif
        </div>
        @if($billsThroughStripe && auth()->user()->hasStripeId())
            <a href="{{ route('plans.billing.portal') }}" class="rounded-md border border-gray-300 px-4 py-2 text-center text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">Manage billing</a>
        @endif
    </div>

    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        @foreach($plans as $plan)
            @php($isCurrentPlan = $currentPlan?->is($plan))
            <div @class([
                'flex flex-col gap-4 rounded-xl bg-white p-6 shadow dark:bg-gray-900',
                'ring-2 ring-indigo-500' => $isCurrentPlan,
            ]) data-plan="{{ $plan->slug }}">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold">{{ $plan->name }}</h2>
                    @if($isCurrentPlan)
                        <span class="rounded-full bg-indigo-600 px-2 py-0.5 text-xs font-medium text-white">Current</span>
                    @elseif($plan->trial_days > 0)
                        <span class="rounded-full bg-fuchsia-100 px-2 py-0.5 text-xs font-medium text-fuchsia-800 dark:bg-fuchsia-950 dark:text-fuchsia-200">{{ $plan->trial_days }}-day free trial</span>
                    @endif
                </div>

                @if($plan->description)
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $plan->description }}</p>
                @endif

                <div class="flex flex-col gap-1">
                    <div><span class="text-3xl font-bold">{{ $plan->formatCents($plan->monthly_price) }}</span> <span class="text-gray-500">/ month</span></div>
                    @if($plan->yearly_price > 0)
                        <div class="text-sm text-gray-500">or {{ $plan->formatCents($plan->yearly_price) }} / year</div>
                    @endif
                </div>

                <ul class="flex flex-col gap-2 text-sm">
                    @foreach(\Sanjayacloud\Plans\Plans::limits() as $limitKey => $limitLabel)
                        <li class="flex items-center gap-2">
                            <span class="text-green-600">&#10003;</span>
                            {{ $limitLabel }}: {{ $plan->limitFor($limitKey) ?? 'Unlimited' }}
                        </li>
                    @endforeach
                    @foreach(\Sanjayacloud\Plans\Plans::features() as $featureKey => $featureLabel)
                        <li @class(['flex items-center gap-2', 'text-gray-400 line-through dark:text-gray-600' => ! $plan->hasFeature($featureKey)])>
                            <span @class(['text-green-600' => $plan->hasFeature($featureKey)])>{!! $plan->hasFeature($featureKey) ? '&#10003;' : '&#10005;' !!}</span>
                            {{ $featureLabel }}
                        </li>
                    @endforeach
                </ul>

                <div class="mt-auto flex flex-col gap-2">
                    @if($isCurrentPlan && $subscription)
                        <a href="{{ route('plans.billing.portal') }}" class="rounded-md border border-gray-300 px-4 py-2 text-center text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">Manage subscription</a>
                    @elseif($plan->isPurchasable() && $subscription)
                        <a href="{{ route('plans.billing.portal') }}" class="rounded-md border border-gray-300 px-4 py-2 text-center text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">Switch in billing portal</a>
                    @elseif($plan->isPurchasable())
                        @foreach([\Sanjayacloud\Plans\Models\Plan::INTERVAL_MONTHLY => 'monthly', \Sanjayacloud\Plans\Models\Plan::INTERVAL_YEARLY => 'yearly'] as $interval => $intervalLabel)
                            @if($plan->stripePriceId($interval))
                                <form method="POST" action="{{ route('plans.billing.checkout', $plan) }}">
                                    @csrf
                                    <input type="hidden" name="interval" value="{{ $interval }}">
                                    <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Subscribe {{ $intervalLabel }}</button>
                                </form>
                            @endif
                        @endforeach
                    @elseif($plan->monthly_price > 0)
                        <button type="button" class="w-full cursor-not-allowed rounded-md bg-gray-200 px-4 py-2 text-sm text-gray-500 dark:bg-gray-800" disabled>Coming soon</button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if($billsThroughStripe)
        <p class="text-center text-sm text-gray-500">Payments are handled securely by Stripe. You can cancel anytime.</p>
    @endif
</div>
@endsection
