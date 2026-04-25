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
        $customer = $this->route('customer');
        
        return [
            'firstName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lastName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', 'unique:users,email,' . $customer->user_id],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'mobilePhone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d])/'],
            'password_confirmation' => ['sometimes', 'nullable', 'string'],
            'trainerId' => ['sometimes', 'nullable', 'exists:users,id'],
            'addressLine1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'addressLine2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postalCode' => ['sometimes', 'nullable', 'string', 'max:20'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'emergencyContact' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'paymentMethod' => ['sometimes', 'nullable', 'string', 'in:cash,invoice,direct_debit'],
            'bankAccountHolder' => ['sometimes', 'required_if:paymentMethod,direct_debit', 'nullable', 'string', 'max:255'],
            'bankIban' => ['sometimes', 'required_if:paymentMethod,direct_debit', 'nullable', 'string', 'max:34', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/'],
            'bankBic' => ['sometimes', 'nullable', 'string', 'max:11', 'regex:/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/'],
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
            'mobilePhone' => 'Mobiltelefon',
            'addressLine1' => 'Adresszeile 1',
            'addressLine2' => 'Adresszeile 2',
            'postalCode' => 'Postleitzahl',
            'city' => 'Stadt',
            'country' => 'Land',
            'emergencyContact' => 'Notfallkontakt',
            'notes' => 'Notizen',
            'paymentMethod' => 'Zahlungsmethode',
            'bankAccountHolder' => 'Kontoinhaber',
            'bankIban' => 'IBAN',
            'bankBic' => 'BIC',
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
        
        if (isset($validated['trainerId'])) {
            $data['trainer_id'] = $validated['trainerId'];
        }
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
        if (array_key_exists('paymentMethod', $validated)) {
            $data['payment_method'] = $validated['paymentMethod'];
        }
        if (array_key_exists('bankAccountHolder', $validated)) {
            $data['bank_account_holder'] = $validated['bankAccountHolder'];
        }
        if (array_key_exists('bankIban', $validated)) {
            $data['bank_iban'] = $validated['bankIban'];
        }
        if (array_key_exists('bankBic', $validated)) {
            $data['bank_bic'] = $validated['bankBic'];
        }
        
        return $data;
    }

    /**
     * Get validated user data.
     *
     * @return array<string, mixed>
     */
    public function validatedUserData(): array
    {
        $validated = $this->validated();
        $data = [];
        
        if (isset($validated['firstName'])) {
            $data['first_name'] = $validated['firstName'];
        }
        if (isset($validated['lastName'])) {
            $data['last_name'] = $validated['lastName'];
        }
        if (isset($validated['email'])) {
            $data['email'] = $validated['email'];
        }
        if (isset($validated['phone'])) {
            $data['phone'] = $validated['phone'];
        }
        if (isset($validated['mobilePhone'])) {
            $data['mobile_phone'] = $validated['mobilePhone'];
        }
        if (!empty($validated['password'])) {
            $data['password'] = \Illuminate\Support\Facades\Hash::make($validated['password']);
        }
        
        return $data;
    }
}
