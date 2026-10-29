<?php

namespace Sanjayacloud\Plans\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Sanjayacloud\Plans\Models\Plan;
use Sanjayacloud\Plans\Plans;

class GrantPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(Plans::adminGate());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', Rule::exists(Plan::class, 'id')->where('is_default_free', false)],
            'granted_until' => ['nullable', 'date', 'after:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_id.required' => 'Choose a plan to grant.',
            'plan_id.exists' => 'Choose a paid plan to grant.',
            'granted_until.after' => 'The grant must end after today, or leave it blank to grant it with no end date.',
        ];
    }
}
