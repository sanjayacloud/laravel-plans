@extends(config('plans.layout', 'plans::layouts.default'))

@section('title', 'Admin Overview')

@section(config('plans.layout_section', 'content'))
<div class="mx-auto flex max-w-6xl flex-col gap-6 px-4">
    @include('plans::admin.partials.nav')
    @include('plans::partials.flash')

    <div>
        <h1 class="text-3xl font-bold">Admin Overview</h1>
        <p class="text-gray-600 dark:text-gray-400">{{ $totalUsers }} {{ Str::plural('user', $totalUsers) }} in total.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        @foreach($statusCounts as $status => $statusCount)
            <a href="{{ route('plans.admin.users.index', ['status' => $status]) }}" class="rounded-xl bg-white p-4 shadow hover:ring-2 hover:ring-indigo-500 dark:bg-gray-900">
                <div class="text-sm text-gray-500">{{ $statusCount['label'] }}</div>
                <div class="text-3xl font-bold" data-status-count="{{ $status }}">{{ $statusCount['count'] }}</div>
            </a>
        @endforeach
        @if($monthlyRecurringRevenue !== null)
            <div class="rounded-xl bg-white p-4 shadow dark:bg-gray-900">
                <div class="text-sm text-gray-500">Estimated MRR</div>
                <div class="text-3xl font-bold text-indigo-600 dark:text-indigo-400" data-mrr>{{ \Sanjayacloud\Plans\Plans::formatCents($monthlyRecurringRevenue) }}</div>
                <div class="text-xs text-gray-500">List prices, before discounts and tax</div>
            </div>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl bg-white p-6 shadow dark:bg-gray-900">
            <h2 class="mb-2 text-lg font-semibold">Recent sign-ups</h2>
            <ul class="divide-y divide-gray-200 dark:divide-gray-800">
                @forelse($recentSignUps as $recentUser)
                    <li class="flex justify-between gap-4 py-2">
                        <a href="{{ route('plans.admin.users.show', $recentUser->getKey()) }}" class="hover:underline">{{ $recentUser->name }}</a>
                        <span class="text-sm text-gray-500">{{ $recentUser->created_at?->diffForHumans() }}</span>
                    </li>
                @empty
                    <li class="py-2 text-gray-500">No users yet.</li>
                @endforelse
            </ul>
        </div>

        @if($billsThroughStripe)
            <div class="rounded-xl bg-white p-6 shadow dark:bg-gray-900">
                <h2 class="mb-2 text-lg font-semibold">Recent cancellations</h2>
                <ul class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse($recentCancellations as $cancelledSubscription)
                        <li class="flex justify-between gap-4 py-2">
                            @if($cancelledSubscription->user)
                                <a href="{{ route('plans.admin.users.show', $cancelledSubscription->user->getKey()) }}" class="hover:underline">{{ $cancelledSubscription->user->name }}</a>
                            @else
                                <span>Deleted user</span>
                            @endif
                            <span class="text-sm text-gray-500">
                                {{ $cancelledSubscription->ends_at->isFuture() ? 'Ends' : 'Ended' }} {{ $cancelledSubscription->ends_at->toFormattedDateString() }}
                            </span>
                        </li>
                    @empty
                        <li class="py-2 text-gray-500">No cancellations.</li>
                    @endforelse
                </ul>
            </div>
        @endif
    </div>
</div>
@endsection
