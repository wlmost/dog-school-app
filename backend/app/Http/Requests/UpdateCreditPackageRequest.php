<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCreditPackageRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'totalCredits' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'price' => ['sometimes', 'numeric', 'min:0', 'max:9999.99'],
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
        $data = [];

        if (isset($validated['name'])) {
            $data['name'] = $validated['name'];
        }
        if (isset($validated['totalCredits'])) {
            $data['total_credits'] = $validated['totalCredits'];
        }
        if (isset($validated['price'])) {
            $data['price'] = $validated['price'];
        }
        if (array_key_exists('validityDays', $validated)) {
            $data['validity_days'] = $validated['validityDays'];
        }
        if (array_key_exists('description', $validated)) {
            $data['description'] = $validated['description'];
        }

        return $data;
    }
}
