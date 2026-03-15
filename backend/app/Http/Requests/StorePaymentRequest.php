<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
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
            'invoiceId' => ['required', 'integer', 'exists:invoices,id'],
            'paymentDate' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'paymentMethod' => ['required', 'in:cash,bank_transfer,paypal,stripe,credit_card'],
            'transactionId' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:pending,completed,failed,refunded'],
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
            'invoiceId' => 'Rechnung',
            'paymentDate' => 'Zahlungsdatum',
            'amount' => 'Betrag',
            'paymentMethod' => 'Zahlungsmethode',
            'transactionId' => 'Transaktions-ID',
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
            'invoice_id' => $validated['invoiceId'],
            'payment_date' => $validated['paymentDate'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['paymentMethod'],
            'transaction_id' => $validated['transactionId'] ?? null,
            'status' => $validated['status'] ?? 'pending',
        ];
    }
}
