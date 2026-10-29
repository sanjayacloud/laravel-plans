@extends(config('plans.layout', 'plans::layouts.default'))

@section('title', $user->name)

@section(config('plans.layout_section', 'content'))
@php
    $cardClasses = 'flex flex-col gap-4 rounded-xl bg-white p-6 shadow dark:bg-gray-900';
    $inputClasses = 'rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950';
    $primaryButton = 'rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500';
    $dangerButton = 'rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950';
    $userKey = $user->getKey();
@endphp
<div class="mx-auto flex max-w-6xl flex-col gap-6 px-4">
    @include('plans::admin.partials.nav')
    @include('plans::partials.flash')

    <div>
        <h1 class="text-3xl font-bold">{{ $user->name }}</h1>
        <p class="text-gray-600 dark:text-gray-400">{{ $user->email }} · joined {{ $user->created_at?->toFormattedDateString() }}</p>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-xl bg-white p-4 shadow dark:bg-gray-900">
            <div class="text-sm text-gray-500">Current plan</div>
            <div class="text-2xl font-bold">{{ $currentPlan?->name ?? 'None' }}</div>
            <div class="text-sm text-gray-500" data-plan-status="{{ $planStatus->value }}">{{ $planStatus->label() }}</div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow dark:bg-gray-900">
            <div class="text-sm text-gray-500">Trial</div>
            <div class="text-2xl font-bold">{{ $user->trial_ends_at?->toFormattedDateString() ?? 'None' }}</div>
            <div class="text-sm text-gray-500">{{ $user->trial_ends_at ? ($user->trial_ends_at->isFuture() ? 'Ends '.$user->trial_ends_at->diffForHumans() : 'Ended') : 'Never started' }}</div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow dark:bg-gray-900">
            <div class="text-sm text-gray-500">Complimentary</div>
            <div class="text-2xl font-bold">{{ $user->grantedPlan?->name ?? 'None' }}</div>
            <div class="text-sm text-gray-500">{{ $user->grantedPlan ? ($user->granted_until ? 'Until '.$user->granted_until->toFormattedDateString() : 'No end date') : '' }}</div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="{{ $cardClasses }}">
            <h2 class="text-lg font-semibold">Trial</h2>
            <form method="POST" action="{{ route('plans.admin.users.trial.update', $userKey) }}" class="flex gap-2">
                @csrf
                @method('PUT')
                <input type="date" name="trial_ends_at" class="{{ $inputClasses }} flex-1" required
                       min="{{ now()->addDay()->toDateString() }}"
                       value="{{ old('trial_ends_at', $user->trial_ends_at?->isFuture() ? $user->trial_ends_at->toDateString() : now()->addDays(14)->toDateString()) }}">
                <button type="submit" class="{{ $primaryButton }}">Set trial end</button>
            </form>
            @if($user->trial_ends_at?->isFuture())
                <form method="POST" action="{{ route('plans.admin.users.trial.end', $userKey) }}"
                      onsubmit="return confirm('End this trial now? The user drops to their next plan straight away.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="{{ $dangerButton }}">End trial now</button>
                </form>
            @endif
        </div>

        <div class="{{ $cardClasses }}">
            <h2 class="text-lg font-semibold">Complimentary plan</h2>
            <form method="POST" action="{{ route('plans.admin.users.grant', $userKey) }}" class="flex flex-col gap-2">
                @csrf
                @method('PUT')
                <div class="flex flex-wrap gap-2">
                    <select name="plan_id" class="{{ $inputClasses }} flex-1" required>
                        @foreach($grantablePlans as $grantablePlan)
                            <option value="{{ $grantablePlan->id }}" @selected((int) old('plan_id', $user->granted_plan_id) === $grantablePlan->id)>{{ $grantablePlan->name }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="granted_until" class="{{ $inputClasses }} flex-1"
                           min="{{ now()->addDay()->toDateString() }}"
                           value="{{ old('granted_until', $user->granted_until?->toDateString()) }}">
                </div>
                <p class="text-sm text-gray-500">Leave the date blank to grant it with no end date.</p>
                <button type="submit" class="{{ $primaryButton }} self-start">{{ $user->grantedPlan ? 'Update grant' : 'Grant plan' }}</button>
            </form>
            @if($user->grantedPlan)
                <form method="POST" action="{{ route('plans.admin.users.grant.revoke', $userKey) }}"
                      onsubmit="return confirm('Revoke the complimentary plan?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="{{ $dangerButton }}">Revoke</button>
                </form>
            @endif
        </div>

        @if($billsThroughStripe)
            <div class="{{ $cardClasses }}">
                <h2 class="text-lg font-semibold">Stripe subscription</h2>
                @if($subscription)
                    <dl class="grid grid-cols-2 gap-2 text-sm">
                        <dt class="text-gray-500">Plan</dt>
                        <dd>{{ $subscriptionPlan?->name ?? 'Unknown price' }}</dd>
                        <dt class="text-gray-500">Price ID</dt>
                        <dd class="font-mono">{{ $subscription->stripe_price }}</dd>
                        <dt class="text-gray-500">Stripe status</dt>
                        <dd>{{ $subscription->stripe_status }}</dd>
                        <dt class="text-gray-500">Ends</dt>
                        <dd>{{ $subscription->ends_at?->toFormattedDateString() ?? 'Renews automatically' }}</dd>
                    </dl>
                    <div class="flex flex-wrap gap-2">
                        @if($subscription->onGracePeriod())
                            <form method="POST" action="{{ route('plans.admin.users.subscription.resume', $userKey) }}">
                                @csrf
                                <button type="submit" class="{{ $primaryButton }}">Resume</button>
                            </form>
                        @elseif($subscription->valid())
                            <form method="POST" action="{{ route('plans.admin.users.subscription.cancel', $userKey) }}"
                                  onsubmit="return confirm('Cancel at the end of the billing period?')">
                                @csrf
                                <button type="submit" class="{{ $dangerButton }}">Cancel at period end</button>
                            </form>
                        @endif
                        @if($subscription->valid())
                            <form method="POST" action="{{ route('plans.admin.users.subscription.cancel', $userKey) }}"
                                  onsubmit="return confirm('Cancel immediately? Access ends now and Stripe stops billing.')">
                                @csrf
                                <input type="hidden" name="immediately" value="1">
                                <button type="submit" class="{{ $dangerButton }}">Cancel now</button>
                            </form>
                        @endif
                    </div>
                @else
                    <p class="text-gray-500">No Stripe subscription.</p>
                @endif
            </div>
        @endif

        <div class="{{ $cardClasses }}">
            <h2 class="text-lg font-semibold">Super admin</h2>
            @if($user->is(auth()->user()))
                <p class="text-gray-500">This is you. Another super admin has to change your access.</p>
            @else
                <p>{{ $user->is_super_admin ? 'This user can manage plans and subscriptions.' : 'This user has no admin access.' }}</p>
                <form method="POST" action="{{ route('plans.admin.users.super-admin', $userKey) }}"
                      onsubmit="return confirm(@js($user->is_super_admin ? 'Remove super admin access?' : 'Make this user a super admin?'))">
                    @csrf
                    <button type="submit" class="{{ $user->is_super_admin ? $dangerButton : $primaryButton }}">
                        {{ $user->is_super_admin ? 'Remove super admin' : 'Make super admin' }}
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="{{ $cardClasses }}">
        <h2 class="text-lg font-semibold">Admin activity</h2>
        <ul class="divide-y divide-gray-200 dark:divide-gray-800">
            @forelse($auditLog as $adminAction)
                <li class="flex flex-wrap justify-between gap-2 py-2" data-audit-action="{{ $adminAction->action }}">
                    <span>
                        <span class="font-medium">{{ Str::of($adminAction->action)->replace('_', ' ')->ucfirst() }}</span>
                        @if($adminAction->details)
                            <span class="text-sm text-gray-500">
                                {{ collect($adminAction->details)->filter(fn ($value) => is_scalar($value))->map(fn ($value, $key) => Str::of($key)->replace('_', ' ').': '.$value)->implode(', ') }}
                            </span>
                        @endif
                    </span>
                    <span class="text-sm text-gray-500">{{ $adminAction->admin?->name ?? 'Deleted admin' }} · {{ $adminAction->created_at->diffForHumans() }}</span>
                </li>
            @empty
                <li class="py-2 text-gray-500">No admin changes yet.</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
