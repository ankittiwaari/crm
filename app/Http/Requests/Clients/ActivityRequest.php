<?php

namespace App\Http\Requests\Clients;

use App\Enums\ActivityType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivityRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ActivityType::class)],
            'body' => ['required', 'string', 'max:5000'],
            'happened_at' => ['nullable', 'date', 'before_or_equal:now'],
            'follow_up_on' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Write a short note about what happened.',
            'follow_up_on.after_or_equal' => 'The follow-up date cannot be in the past.',
        ];
    }
}
