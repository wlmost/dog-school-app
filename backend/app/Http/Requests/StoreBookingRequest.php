<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Store Booking Request
 *
 * Validates incoming requests to create a new booking.
 */
class StoreBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Admins, trainers, and customers can create bookings
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'trainingSessionId' => ['required', 'integer', 'exists:training_sessions,id'],
            'customerId' => ['required', 'integer', 'exists:customers,id'],
            'dogId' => ['required', 'integer', 'exists:dogs,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get validated data with snake_case keys for database.
     *
     * @return array<string, mixed>
     */
    public function validatedSnakeCase(): array
    {
        $validated = $this->validated();
        $snakeCase = [];

        foreach ($validated as $key => $value) {
            $snakeCase[Str::snake($key)] = $value;
        }

        // Add booking_date as current date
        $snakeCase['booking_date'] = now();
        $snakeCase['status'] = 'pending';

        return $snakeCase;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'trainingSessionId' => 'training session',
            'customerId' => 'customer',
            'dogId' => 'dog',
        ];
    }
}
