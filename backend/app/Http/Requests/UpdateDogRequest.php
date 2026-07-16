<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Update Dog Request
 *
 * Validates incoming requests to update an existing dog.
 */
class UpdateDogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }
        if ($user->isAdminOrTrainer()) {
            return true;
        }
        // Customers can update their own dogs
        if ($user->isCustomer()) {
            $dog = $this->route('dog');

            return $dog && $dog->customer?->user_id === $user->id;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $dogId = $this->route('dog')?->id;

        return [
            'customerId' => ['sometimes', 'integer', 'exists:customers,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'breed' => ['sometimes', 'string', 'max:255'],
            'dateOfBirth' => ['sometimes', 'date', 'before:today'],
            'gender' => ['sometimes', 'in:male,female'],
            'chipNumber' => ['nullable', 'string', 'max:50', Rule::unique('dogs', 'chip_number')->ignore($dogId)],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'color' => ['nullable', 'string', 'max:100'],
            'specialNeeds' => ['nullable', 'string', 'max:1000'],
            'veterinarian' => ['nullable', 'string', 'max:255'],
            'neutered' => ['nullable', 'boolean'],
            'isActive' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'ownerSince' => ['nullable', 'date', 'before_or_equal:today'],
            'ageAtAcquisition' => ['nullable', 'string', 'max:255'],
            'origin' => ['nullable', 'in:breeder,shelter,private,unknown'],
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
            'customerId' => 'customer ID',
            'dateOfBirth' => 'date of birth',
            'chipNumber' => 'chip number',
            'specialNeeds' => 'special needs',
            'isActive' => 'active status',
            'ownerSince' => 'owner since date',
            'ageAtAcquisition' => 'age at acquisition',
        ];
    }
}
