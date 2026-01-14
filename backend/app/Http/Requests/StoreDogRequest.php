<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Store Dog Request
 *
 * Validates incoming requests to create a new dog.
 */
class StoreDogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only admins and trainers can create dogs
        return $this->user()?->isAdminOrTrainer() ?? false;
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
            'name' => ['required', 'string', 'max:255'],
            'breed' => ['required', 'string', 'max:255'],
            'dateOfBirth' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'in:male,female'],
            'chipNumber' => ['nullable', 'string', 'max:50', 'unique:dogs,chip_number'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'color' => ['nullable', 'string', 'max:100'],
            'specialNeeds' => ['nullable', 'string', 'max:1000'],
            'veterinarian' => ['nullable', 'string', 'max:255'],
            'isActive' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
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
        ];
    }
}
