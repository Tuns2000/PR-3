<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IssFetchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // No auth required for public API
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // No parameters required for fetch
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            // No custom messages needed
        ];
    }
}
