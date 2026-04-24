<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerCreditRequest extends FormRequest
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
            'remainingCredits' => ['sometimes', 'integer', 'min:0'],
            'expirationDate' => ['nullable', 'date'],
            'status' => ['sometimes', 'in:active,expired,used'],
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
            'remainingCredits' => 'Verbleibende Einheiten',
            'expirationDate' => 'Ablaufdatum',
            'status' => 'Status',
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

        if (isset($validated['remainingCredits'])) {
            $data['remaining_credits'] = $validated['remainingCredits'];
        }
        if (array_key_exists('expirationDate', $validated)) {
            $data['expiration_date'] = $validated['expirationDate'];
        }
        if (isset($validated['status'])) {
            $data['status'] = $validated['status'];
        }

        return $data;
    }
}
