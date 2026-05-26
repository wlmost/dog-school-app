<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * BookCourseRunRequest
 *
 * Validates requests to book all sessions in a CourseRun for a customer/dog pair.
 * Customers may only book for their own customer record.
 */
class BookCourseRunRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Customers may only book for their own customer record.
     * Trainers may book on behalf of any customer.
     * Admins are not permitted to create bookings.
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
            'customerId' => ['required', 'integer', 'exists:customers,id'],
            'dogId'      => ['required', 'integer', 'exists:dogs,id'],
            'notes'      => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Convert camelCase validated data to snake_case keys for database usage.
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

        return $snakeCase;
    }

    /**
     * Get custom display names for validation attributes.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customerId' => 'customer',
            'dogId'      => 'dog',
        ];
    }
}
