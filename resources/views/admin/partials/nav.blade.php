<nav class="flex flex-wrap gap-2 text-sm">
    @foreach([
        ['route' => 'plans.admin.dashboard', 'active' => 'plans.admin.dashboard', 'label' => 'Overview'],
        ['route' => 'plans.admin.plans.index', 'active' => 'plans.admin.plans.*', 'label' => 'Plans'],
        ['route' => 'plans.admin.users.index', 'active' => 'plans.admin.users.*', 'label' => 'Users'],
    ] as $navItem)
        <a href="{{ route($navItem['route']) }}" @class([
            'rounded-md px-3 py-1.5 font-medium',
            'bg-indigo-600 text-white' => request()->routeIs($navItem['active']),
            'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' => ! request()->routeIs($navItem['active']),
        ])>{{ $navItem['label'] }}</a>
    @endforeach
</nav>
