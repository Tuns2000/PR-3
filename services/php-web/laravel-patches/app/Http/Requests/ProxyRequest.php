<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProxyRequest extends FormRequest
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
            'path' => [
                'nullable',
                'string',
                'max:500',
                'regex:/^[a-zA-Z0-9\/_\-\.]+$/', // Allow dots for file extensions
                'not_regex:/\.\.|%00|%2e%2e/', // Prevent path traversal and null bytes
            ],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'path.regex' => 'Path contains invalid characters. Only alphanumeric, slash, underscore, and hyphen are allowed.',
            'path.max' => 'Path is too long (maximum 500 characters)',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize path parameter from route
        $this->merge([
            'path' => $this->route('path'),
        ]);
    }
}
