<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IssHistoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'start' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'end' => 'nullable|date_format:Y-m-d|after_or_equal:start|before_or_equal:today',
            'limit' => 'nullable|integer|min:1|max:1000',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'start.date_format' => 'Start date must be in YYYY-MM-DD format',
            'start.before_or_equal' => 'Start date cannot be in the future',
            'end.date_format' => 'End date must be in YYYY-MM-DD format',
            'end.after_or_equal' => 'End date must be after or equal to start date',
            'end.before_or_equal' => 'End date cannot be in the future',
            'limit.integer' => 'Limit must be an integer',
            'limit.min' => 'Limit must be at least 1',
            'limit.max' => 'Limit cannot exceed 1000',
        ];
    }

    /**
     * Get validated data with defaults.
     * This will throw ValidationException if data is invalid.
     */
    public function validated($key = null, $default = null)
    {
        // parent::validated() will throw ValidationException if invalid
        $validated = parent::validated();
        
        // Only apply defaults if validation passed
        return [
            'start' => $validated['start'] ?? null,
            'end' => $validated['end'] ?? null,
            'limit' => $validated['limit'] ?? 100,
        ];
    }
}
