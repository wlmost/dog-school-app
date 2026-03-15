<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerCreditRequest extends FormRequest
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
            'customerId' => ['required', 'integer', 'exists:customers,id'],
            'creditPackageId' => ['required', 'integer', 'exists:credit_packages,id'],
            'totalCredits' => ['required', 'integer', 'min:1'],
            'remainingCredits' => ['required', 'integer', 'min:0', 'lte:totalCredits'],
            'purchaseDate' => ['required', 'date', 'before_or_equal:today'],
            'expirationDate' => ['nullable', 'date', 'after:purchaseDate'],
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
            'customerId' => 'Kunde',
            'creditPackageId' => 'Paket',
            'totalCredits' => 'Gesamt Einheiten',
            'remainingCredits' => 'Verbleibende Einheiten',
            'purchaseDate' => 'Kaufdatum',
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
        
        return [
            'customer_id' => $validated['customerId'],
            'credit_package_id' => $validated['creditPackageId'],
            'total_credits' => $validated['totalCredits'],
            'remaining_credits' => $validated['remainingCredits'],
            'purchase_date' => $validated['purchaseDate'],
            'expiration_date' => $validated['expirationDate'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ];
    }
}
