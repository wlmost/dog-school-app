<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCreditPackageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('admin') || $this->user()->can('trainer');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'totalCredits' => ['required', 'integer', 'min:1', 'max:100'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'validityDays' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Name',
            'totalCredits' => 'Anzahl Einheiten',
            'price' => 'Preis',
            'validityDays' => 'Gültigkeitstage',
            'description' => 'Beschreibung',
        ];
    }

    /**
     * Get the validated data from the request as snake_case.
     *
     * @return array<string, mixed>
     */
    public function validatedSnakeCase(): array
    {
        $validated = $this->validated();
        
        return [
            'name' => $validated['name'],
            'total_credits' => $validated['totalCredits'],
            'price' => $validated['price'],
            'validity_days' => $validated['validityDays'] ?? null,
            'description' => $validated['description'] ?? null,
        ];
    }
}
