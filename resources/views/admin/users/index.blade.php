@extends(config('plans.layout', 'plans::layouts.default'))

@section('title', 'Users & Subscriptions')

@section(config('plans.layout_section', 'content'))
<div class="mx-auto flex max-w-6xl flex-col gap-6 px-4">
    @include('plans::admin.partials.nav')
    @include('plans::partials.flash')

    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-3xl font-bold">Users &amp; Subscriptions</h1>
        <form method="GET" action="{{ route('plans.admin.users.index') }}" class="flex flex-wrap gap-2">
            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name or email"
                   class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950">
            <select name="status" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950">
                <option value="">All statuses</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Filter</button>
        </form>
    </div>

    <div class="overflow-x-auto rounded-xl bg-white shadow dark:bg-gray-900">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
            <thead class="text-left text-gray-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Name</th>
                    <th class="px-4 py-3 font-medium">Email</th>
                    <th class="px-4 py-3 font-medium">Plan</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">Trial ends</th>
                    <th class="px-4 py-3 font-medium">Joined</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                @forelse($users as $listedUser)
                    <tr data-user-row="{{ $listedUser->getKey() }}">
                        <td class="px-4 py-3">
                            <a href="{{ route('plans.admin.users.show', $listedUser->getKey()) }}" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ $listedUser->name }}</a>
                            @if($listedUser->is_super_admin)
                                <span class="ml-1 rounded-full bg-indigo-100 px-2 py-0.5 text-xs text-indigo-800 dark:bg-indigo-950 dark:text-indigo-200">Admin</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $listedUser->email }}</td>
                        <td class="px-4 py-3">{{ $listedUser->currentPlan()?->name ?? '—' }}</td>
                        <td class="px-4 py-3" data-user-status="{{ $listedUser->planStatus()->value }}">{{ $listedUser->planStatus()->label() }}</td>
                        <td class="px-4 py-3">{{ $listedUser->trial_ends_at?->toDateString() ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $listedUser->created_at?->toDateString() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">No users match.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
</div>
@endsection
