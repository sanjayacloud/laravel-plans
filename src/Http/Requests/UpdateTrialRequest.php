<?php

namespace Sanjayacloud\Plans\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Sanjayacloud\Plans\Plans;

class UpdateTrialRequest extends FormRequest
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
            'trial_ends_at' => ['required', 'date', 'after:today', 'before:+2 years'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'trial_ends_at.required' => 'Choose the date the trial should end.',
            'trial_ends_at.after' => 'The trial must end after today. Use "End trial now" to stop it.',
            'trial_ends_at.before' => 'A trial can be extended by up to two years.',
        ];
    }
}
