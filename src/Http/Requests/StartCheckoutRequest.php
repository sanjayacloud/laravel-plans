<?php

namespace Sanjayacloud\Plans\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Sanjayacloud\Plans\Models\Plan;

class StartCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'interval' => ['required', Rule::in([Plan::INTERVAL_MONTHLY, Plan::INTERVAL_YEARLY])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'interval.required' => 'Choose monthly or yearly billing.',
            'interval.in' => 'Choose monthly or yearly billing.',
        ];
    }
}
