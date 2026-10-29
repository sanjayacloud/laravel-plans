<?php

namespace Sanjayacloud\Plans\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Sanjayacloud\Plans\Models\Plan;
use Sanjayacloud\Plans\Plans;

class SavePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(Plans::adminGate());
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug($this->input('slug') ?: $this->input('name', '')),
            'stripe_monthly_price_id' => $this->filled('stripe_monthly_price_id') ? trim($this->input('stripe_monthly_price_id')) : null,
            'stripe_yearly_price_id' => $this->filled('stripe_yearly_price_id') ? trim($this->input('stripe_yearly_price_id')) : null,
            'features' => array_values((array) $this->input('features', [])),
            'is_default_free' => $this->boolean('is_default_free'),
            'is_trial_plan' => $this->boolean('is_trial_plan'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $plan = $this->route('plan');
        $stripePriceRule = ['nullable', 'string', 'max:255', 'regex:/^price_[A-Za-z0-9]+$/'];

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', Rule::unique(Plan::class, 'slug')->ignore($plan)],
            'description' => ['nullable', 'string', 'max:500'],
            'monthly_price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'yearly_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'stripe_monthly_price_id' => [...$stripePriceRule, Rule::unique(Plan::class, 'stripe_monthly_price_id')->ignore($plan), 'different:stripe_yearly_price_id'],
            'stripe_yearly_price_id' => [...$stripePriceRule, Rule::unique(Plan::class, 'stripe_yearly_price_id')->ignore($plan)],
            'features' => ['array'],
            'features.*' => ['string', Rule::in(array_keys(Plans::features()))],
            'limits' => ['array'],
            'limits.*' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_default_free' => ['boolean'],
            'is_trial_plan' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $plan = $this->route('plan');

                if ($plan?->is_default_free && ! $this->boolean('is_default_free')) {
                    $validator->errors()->add('is_default_free', 'There must always be one default free plan. Make another plan the default instead.');
                }

                if ($this->boolean('is_default_free') && ($this->filled('stripe_monthly_price_id') || $this->filled('stripe_yearly_price_id'))) {
                    $validator->errors()->add('is_default_free', 'The default free plan cannot have Stripe prices.');
                }

                if ($this->boolean('is_default_free') && ! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', 'The default free plan must stay active.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'Another plan already uses this slug.',
            'stripe_monthly_price_id.regex' => 'Stripe price IDs start with "price_", for example price_1Abc23.',
            'stripe_yearly_price_id.regex' => 'Stripe price IDs start with "price_", for example price_1Abc23.',
            'stripe_monthly_price_id.unique' => 'Another plan already uses this monthly price ID.',
            'stripe_yearly_price_id.unique' => 'Another plan already uses this yearly price ID.',
            'stripe_monthly_price_id.different' => 'The monthly and yearly price IDs must be different.',
            'features.*.in' => 'One of the selected features does not exist.',
            'limits.*.integer' => 'Limits must be whole numbers.',
            'limits.*.min' => 'Limits must be at least 1, or left blank for unlimited.',
        ];
    }

    /**
     * Validated data ready to save: prices in cents, unknown limits dropped and blank limits removed (unlimited).
     *
     * @return array<string, mixed>
     */
    public function planAttributes(): array
    {
        $validated = $this->validated();

        return [
            ...$validated,
            'monthly_price' => (int) round($validated['monthly_price'] * 100),
            'yearly_price' => (int) round($validated['yearly_price'] * 100),
            'features' => $validated['features'] ?? [],
            'limits' => array_map('intval', array_filter(
                array_intersect_key($validated['limits'] ?? [], Plans::limits()),
                fn ($value) => $value !== null && $value !== '',
            )),
        ];
    }
}
