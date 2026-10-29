@extends(config('plans.layout', 'plans::layouts.default'))

@section('title', 'New Plan')

@section(config('plans.layout_section', 'content'))
<div class="mx-auto flex max-w-3xl flex-col gap-6 px-4">
    @include('plans::admin.partials.nav')
    @include('plans::partials.flash')

    <form method="POST" action="{{ route('plans.admin.plans.store') }}" class="flex flex-col gap-4 rounded-xl bg-white p-6 shadow dark:bg-gray-900">
        @csrf
        <h1 class="text-2xl font-semibold">New plan</h1>

        @include('plans::admin.plans.form')

        <div class="flex justify-end gap-2">
            <a href="{{ route('plans.admin.plans.index') }}" class="rounded-md px-4 py-2 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800">Cancel</a>
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Create plan</button>
        </div>
    </form>
</div>
@endsection
