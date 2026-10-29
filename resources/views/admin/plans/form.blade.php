@php
    $centsToDollars = fn (?int $cents): string => number_format(($cents ?? 0) / 100, 2, '.', '');
    $selectedFeatures = old('features', $plan->features ?? []);
    $inputClasses = 'w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950';
    $labelClasses = 'flex flex-col gap-1 text-sm font-medium';
@endphp

<div class="grid gap-4 md:grid-cols-2">
    <label class="{{ $labelClasses }}">
        Name
        <input type="text" name="name" value="{{ old('name', $plan->name) }}" class="{{ $inputClasses }}" required maxlength="100">
    </label>
    <label class="{{ $labelClasses }}">
        <span>Slug <span class="font-normal text-gray-500">(blank uses the name)</span></span>
        <input type="text" name="slug" value="{{ old('slug', $plan->slug) }}" class="{{ $inputClasses }}" maxlength="100">
    </label>
</div>

<label class="{{ $labelClasses }}">
    Description
    <textarea name="description" class="{{ $inputClasses }}" rows="2" maxlength="500">{{ old('description', $plan->description) }}</textarea>
</label>

<div class="grid gap-4 md:grid-cols-2">
    <label class="{{ $labelClasses }}">
        Monthly price
        <input type="number" name="monthly_price" step="0.01" min="0" value="{{ old('monthly_price', $centsToDollars($plan->monthly_price)) }}" class="{{ $inputClasses }}" required>
    </label>
    <label class="{{ $labelClasses }}">
        Yearly price
        <input type="number" name="yearly_price" step="0.01" min="0" value="{{ old('yearly_price', $centsToDollars($plan->yearly_price)) }}" class="{{ $inputClasses }}" required>
    </label>
    <label class="{{ $labelClasses }}">
        Stripe monthly price ID
        <input type="text" name="stripe_monthly_price_id" value="{{ old('stripe_monthly_price_id', $plan->stripe_monthly_price_id) }}" class="{{ $inputClasses }} font-mono" placeholder="price_...">
    </label>
    <label class="{{ $labelClasses }}">
        Stripe yearly price ID
        <input type="text" name="stripe_yearly_price_id" value="{{ old('stripe_yearly_price_id', $plan->stripe_yearly_price_id) }}" class="{{ $inputClasses }} font-mono" placeholder="price_...">
    </label>
</div>
<p class="text-sm text-gray-500">The prices above are what users see. Make sure they match the Stripe prices, because Stripe charges the linked price.</p>

@if(\Sanjayacloud\Plans\Plans::features() !== [])
    <fieldset class="flex flex-col gap-2">
        <legend class="mb-2 font-semibold">Features</legend>
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach(\Sanjayacloud\Plans\Plans::features() as $featureKey => $featureLabel)
                <label class="flex items-center gap-3 text-sm">
                    <input type="checkbox" name="features[]" value="{{ $featureKey }}" class="rounded border-gray-300"
                           @checked(in_array($featureKey, $selectedFeatures, true))>
                    {{ $featureLabel }}
                </label>
            @endforeach
        </div>
    </fieldset>
@endif

@if(\Sanjayacloud\Plans\Plans::limits() !== [])
    <fieldset>
        <legend class="mb-2 font-semibold">Limits <span class="text-sm font-normal text-gray-500">(blank means unlimited)</span></legend>
        <div class="grid gap-4 md:grid-cols-2">
            @foreach(\Sanjayacloud\Plans\Plans::limits() as $limitKey => $limitLabel)
                <label class="{{ $labelClasses }}">
                    {{ $limitLabel }}
                    <input type="number" name="limits[{{ $limitKey }}]" min="1" value="{{ old('limits.'.$limitKey, $plan->limits[$limitKey] ?? '') }}" class="{{ $inputClasses }}" placeholder="Unlimited">
                </label>
            @endforeach
        </div>
    </fieldset>
@endif

<div class="grid gap-4 md:grid-cols-2">
    <label class="{{ $labelClasses }}">
        Trial days for new users
        <input type="number" name="trial_days" min="0" max="365" value="{{ old('trial_days', $plan->trial_days ?? 0) }}" class="{{ $inputClasses }}" required>
    </label>
    <label class="{{ $labelClasses }}">
        Sort order
        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" class="{{ $inputClasses }}" required>
    </label>
</div>

<div class="flex flex-wrap gap-6 text-sm">
    <label class="flex items-center gap-2">
        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300" @checked(old('is_active', $plan->is_active))>
        Active (shown on the pricing page)
    </label>
    <label class="flex items-center gap-2">
        <input type="checkbox" name="is_trial_plan" value="1" class="rounded border-gray-300" @checked(old('is_trial_plan', $plan->is_trial_plan))>
        New users trial this plan
    </label>
    <label class="flex items-center gap-2">
        <input type="checkbox" name="is_default_free" value="1" class="rounded border-gray-300" @checked(old('is_default_free', $plan->is_default_free))>
        Default free plan
    </label>
</div>
