<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class AstronomyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'elevation' => ['required', 'integer', 'min:0', 'max:5000'],
            'from_date' => ['required', 'date', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'time' => ['required', 'date_format:H:i:s'],
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.between' => 'Latitude must be between -90 and 90 degrees.',
            'longitude.between' => 'Longitude must be between -180 and 180 degrees.',
            'elevation.max' => 'Elevation cannot exceed 5000 meters.',
            'from_date.date_format' => 'From date must be in Y-m-d format.',
            'to_date.date_format' => 'To date must be in Y-m-d format.',
            'to_date.after_or_equal' => 'To date must be after or equal to from date.',
            'time.date_format' => 'Time must be in H:i:s format.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'ok' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid astronomy request parameters.',
                    'details' => $validator->errors()
                ]
            ], 422)
        );
    }
}
