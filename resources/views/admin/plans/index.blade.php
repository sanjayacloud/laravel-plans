@extends(config('plans.layout', 'plans::layouts.default'))

@section('title', 'Plans')

@section(config('plans.layout_section', 'content'))
<div class="mx-auto flex max-w-6xl flex-col gap-6 px-4">
    @include('plans::admin.partials.nav')
    @include('plans::partials.flash')

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold">Plans</h1>
            <p class="text-gray-600 dark:text-gray-400">Prices are shown to users; Stripe charges the price IDs you link here.</p>
        </div>
        <a href="{{ route('plans.admin.plans.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">New plan</a>
    </div>

    <div class="overflow-x-auto rounded-xl bg-white shadow dark:bg-gray-900">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
            <thead class="text-left text-gray-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Order</th>
                    <th class="px-4 py-3 font-medium">Plan</th>
                    <th class="px-4 py-3 font-medium">Monthly</th>
                    <th class="px-4 py-3 font-medium">Yearly</th>
                    <th class="px-4 py-3 font-medium">Stripe</th>
                    <th class="px-4 py-3 font-medium">Trial</th>
                    <th class="px-4 py-3 font-medium">Subscribers</th>
                    <th class="px-4 py-3 font-medium">Complimentary</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                @foreach($plans as $plan)
                    <tr data-plan-row="{{ $plan->slug }}">
                        <td class="px-4 py-3">{{ $plan->sort_order }}</td>
                        <td class="px-4 py-3">
                            <div class="font-semibold">{{ $plan->name }}</div>
                            <div class="mt-1 flex flex-wrap gap-1 text-xs">
                                @if($plan->is_default_free)
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 dark:bg-gray-800">Default free</span>
                                @endif
                                @if($plan->is_trial_plan)
                                    <span class="rounded-full bg-fuchsia-100 px-2 py-0.5 text-fuchsia-800 dark:bg-fuchsia-950 dark:text-fuchsia-200">Trial plan</span>
                                @endif
                                @unless($plan->is_active)
                                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-red-800 dark:bg-red-950 dark:text-red-200">Inactive</span>
                                @endunless
                            </div>
                        </td>
                        <td class="px-4 py-3">{{ $plan->formatCents($plan->monthly_price) }}</td>
                        <td class="px-4 py-3">{{ $plan->formatCents($plan->yearly_price) }}</td>
                        <td class="px-4 py-3">
                            @if($plan->stripe_monthly_price_id || $plan->stripe_yearly_price_id)
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-800 dark:bg-green-950 dark:text-green-200">Linked</span>
                            @elseif(! $plan->is_default_free)
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-200">No price IDs</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $plan->trial_days ? $plan->trial_days.' days' : '—' }}</td>
                        <td class="px-4 py-3">{{ $plan->active_subscriptions_count }}</td>
                        <td class="px-4 py-3">{{ $plan->granted_users_count }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-3">
                                <a href="{{ route('plans.admin.plans.edit', $plan) }}" class="text-indigo-600 hover:underline dark:text-indigo-400">Edit</a>
                                @unless($plan->is_default_free)
                                    <form action="{{ route('plans.admin.plans.destroy', $plan) }}" method="POST"
                                          onsubmit="return confirm(@js('Delete the '.$plan->name.' plan?'))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline dark:text-red-400">Delete</button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
