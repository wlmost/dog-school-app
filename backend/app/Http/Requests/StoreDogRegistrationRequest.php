<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Store Dog Registration Request
 *
 * Validates a customer's request to register a new dog.
 * Only authenticated customers may submit this request.
 */
class StoreDogRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Only customers may submit dog registration requests.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()?->isCustomer() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'breed'       => ['nullable', 'string', 'max:255'],
            'gender'      => ['nullable', 'in:male,female'],
            'dateOfBirth' => ['nullable', 'date', 'before_or_equal:today'],
            'neutered'    => ['nullable', 'boolean'],
            'chipNumber'  => ['nullable', 'string', 'max:50'],
            'notes'       => ['nullable', 'string', 'max:1000'],
            'ownerSince'       => ['nullable', 'date', 'before_or_equal:today'],
            'ageAtAcquisition' => ['nullable', 'string', 'max:255'],
            'origin'           => ['nullable', 'in:breeder,shelter,private,unknown'],
        ];
    }

    /**
     * Get custom attribute names for validator error messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'dateOfBirth' => 'date of birth',
            'chipNumber'  => 'chip number',
            'ownerSince'       => 'owner since date',
            'ageAtAcquisition' => 'age at acquisition',
        ];
    }

    /**
     * Return validated data with keys converted to snake_case for database use.
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
}
