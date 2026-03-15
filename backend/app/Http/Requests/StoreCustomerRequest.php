<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Store Customer Request
 *
 * Validates data for creating a new customer.
 */
class StoreCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only admins and trainers can create customers
        return $this->user()?->isAdmin() || $this->user()?->isTrainer();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'userId' => ['required', 'integer', 'exists:users,id', 'unique:customers,user_id'],
            'addressLine1' => ['nullable', 'string', 'max:255'],
            'addressLine2' => ['nullable', 'string', 'max:255'],
            'postalCode' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'emergencyContact' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'userId' => 'Benutzer-ID',
            'addressLine1' => 'Adresszeile 1',
            'addressLine2' => 'Adresszeile 2',
            'postalCode' => 'Postleitzahl',
            'city' => 'Stadt',
            'country' => 'Land',
            'emergencyContact' => 'Notfallkontakt',
            'notes' => 'Notizen',
        ];
    }

    /**
     * Get the validated data from the request with snake_case keys.
     *
     * @return array<string, mixed>
     */
    public function validatedSnakeCase(): array
    {
        $validated = $this->validated();
        
        return [
            'user_id' => $validated['userId'],
            'address_line1' => $validated['addressLine1'] ?? null,
            'address_line2' => $validated['addressLine2'] ?? null,
            'postal_code' => $validated['postalCode'] ?? null,
            'city' => $validated['city'] ?? null,
            'country' => $validated['country'] ?? null,
            'emergency_contact' => $validated['emergencyContact'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];
    }
}
