<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Customer;
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
     *
     * Customers and trainers can create bookings.
     * Admins have read-only access and cannot create bookings.
     * Customers may only create bookings for themselves.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! ($user->isCustomer() || $user->isTrainer())) {
            return false;
        }

        // Customers can only create bookings for their own customer record
        if ($user->isCustomer()) {
            $customerRecord = Customer::where('user_id', $user->id)->first();

            if ($customerRecord === null) {
                return false;
            }

            return (int) $this->input('customerId') === $customerRecord->id;
        }

        return true;
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
