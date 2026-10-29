@use('Sanjayacloud\Plans\Enums\PlanStatus')
@php
    $bannerUser = auth()->user();
    $bannerStatus = $bannerUser && method_exists($bannerUser, 'planStatus') ? $bannerUser->planStatus() : null;
    $trialEndedRecently = $bannerStatus === PlanStatus::Free
        && $bannerUser->trial_ends_at !== null
        && $bannerUser->trial_ends_at->isAfter(now()->subDays(30));
    $billingUrl = Route::has('plans.billing.index') ? route('plans.billing.index') : null;
    $bannerClasses = 'mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border px-4 py-3 text-sm';
    $linkClasses = 'rounded-md bg-indigo-600 px-3 py-1.5 font-medium text-white hover:bg-indigo-500';
@endphp

@if($bannerStatus === PlanStatus::Trial)
    @php($trialDaysLeft = max(1, (int) ceil(now()->diffInDays($bannerUser->trial_ends_at))))
    <div {{ $attributes->merge(['class' => $bannerClasses.' border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200']) }} data-plan-banner="trial">
        <span>{{ $bannerUser->currentPlan()?->name }} trial: {{ $trialDaysLeft }} {{ Str::plural('day', $trialDaysLeft) }} left.</span>
        @if($billingUrl)
            <a href="{{ $billingUrl }}" class="{{ $linkClasses }}">Choose a plan</a>
        @endif
    </div>
@elseif($trialEndedRecently)
    <div {{ $attributes->merge(['class' => $bannerClasses.' border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200']) }} data-plan-banner="trial-ended">
        <span>Your trial ended. You're on the {{ $bannerUser->currentPlan()?->name ?? 'free' }} plan now.</span>
        @if($billingUrl)
            <a href="{{ $billingUrl }}" class="{{ $linkClasses }}">Upgrade</a>
        @endif
    </div>
@elseif($bannerStatus === PlanStatus::GracePeriod)
    <div {{ $attributes->merge(['class' => $bannerClasses.' border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200']) }} data-plan-banner="grace-period">
        <span>Your subscription is cancelled and ends on {{ $bannerUser->subscription(\Sanjayacloud\Plans\Plans::subscriptionType())?->ends_at?->toFormattedDateString() }}.</span>
        @if($billingUrl)
            <a href="{{ $billingUrl }}" class="{{ $linkClasses }}">Manage plan</a>
        @endif
    </div>
@endif
