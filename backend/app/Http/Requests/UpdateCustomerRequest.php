<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update Customer Request
 *
 * Validates data for updating an existing customer.
 */
class UpdateCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $customer = $this->route('customer');
        
        // Admins and trainers can update any customer
        if ($this->user()?->isAdmin() || $this->user()?->isTrainer()) {
            return true;
        }
        
        // Customers can only update their own profile
        return $this->user()?->customer?->id === $customer?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'addressLine1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'addressLine2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postalCode' => ['sometimes', 'nullable', 'string', 'max:20'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'emergencyContact' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
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
        $data = [];
        
        if (isset($validated['addressLine1'])) {
            $data['address_line1'] = $validated['addressLine1'];
        }
        if (isset($validated['addressLine2'])) {
            $data['address_line2'] = $validated['addressLine2'];
        }
        if (isset($validated['postalCode'])) {
            $data['postal_code'] = $validated['postalCode'];
        }
        if (isset($validated['city'])) {
            $data['city'] = $validated['city'];
        }
        if (isset($validated['country'])) {
            $data['country'] = $validated['country'];
        }
        if (isset($validated['emergencyContact'])) {
            $data['emergency_contact'] = $validated['emergencyContact'];
        }
        if (isset($validated['notes'])) {
            $data['notes'] = $validated['notes'];
        }
        
        return $data;
    }
}
