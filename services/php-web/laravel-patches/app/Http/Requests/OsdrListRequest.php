<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OsdrListRequest extends FormRequest
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
            'limit' => 'nullable|integer|min:1|max:500',
            'page' => 'nullable|integer|min:1',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'limit.integer' => 'Limit must be an integer',
            'limit.min' => 'Limit must be at least 1',
            'limit.max' => 'Limit cannot exceed 500',
            'page.integer' => 'Page must be an integer',
            'page.min' => 'Page must be at least 1',
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
            'limit' => $validated['limit'] ?? 50,
            'page' => $validated['page'] ?? 1,
        ];
    }
}
