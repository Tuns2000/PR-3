<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LegacyViewRequest extends FormRequest
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
            'filename' => 'required|string|max:255|regex:/^[a-zA-Z0-9_-]+\.csv$/',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'filename.required' => 'Filename is required',
            'filename.regex' => 'Invalid filename format. Only alphanumeric characters, underscores, hyphens, and .csv extension are allowed.',
            'filename.max' => 'Filename is too long (maximum 255 characters)',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize filename parameter from route
        $this->merge([
            'filename' => $this->route('filename'),
        ]);
    }
}
