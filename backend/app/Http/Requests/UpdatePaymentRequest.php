<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
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
            'paymentDate' => ['sometimes', 'date'],
            'amount' => ['sometimes', 'numeric', 'min:0.01', 'max:99999999.99'],
            'paymentMethod' => ['sometimes', 'in:cash,bank_transfer,paypal,stripe,credit_card'],
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
        $data = [];

        if (isset($validated['paymentDate'])) {
            $data['payment_date'] = $validated['paymentDate'];
        }
        if (isset($validated['amount'])) {
            $data['amount'] = $validated['amount'];
        }
        if (isset($validated['paymentMethod'])) {
            $data['payment_method'] = $validated['paymentMethod'];
        }
        if (array_key_exists('transactionId', $validated)) {
            $data['transaction_id'] = $validated['transactionId'];
        }
        if (isset($validated['status'])) {
            $data['status'] = $validated['status'];
        }

        return $data;
    }
}
