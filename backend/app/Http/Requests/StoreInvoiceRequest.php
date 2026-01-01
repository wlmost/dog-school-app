<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
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
            'invoiceNumber' => ['required', 'string', 'max:255', 'unique:invoices,invoice_number'],
            'status' => ['sometimes', 'in:draft,sent,paid,overdue,cancelled'],
            'totalAmount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'issueDate' => ['required', 'date'],
            'dueDate' => ['required', 'date', 'after_or_equal:issueDate'],
            'paidDate' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['sometimes', 'array'],
            'items.*.description' => ['required_with:items', 'string', 'max:500'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unitPrice' => ['required_with:items', 'numeric', 'min:0'],
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
            'invoiceNumber' => 'Rechnungsnummer',
            'status' => 'Status',
            'totalAmount' => 'Gesamtbetrag',
            'issueDate' => 'Rechnungsdatum',
            'dueDate' => 'Fälligkeitsdatum',
            'paidDate' => 'Bezahldatum',
            'notes' => 'Notizen',
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
            'invoice_number' => $validated['invoiceNumber'],
            'status' => $validated['status'] ?? 'draft',
            'total_amount' => $validated['totalAmount'],
            'issue_date' => $validated['issueDate'],
            'due_date' => $validated['dueDate'],
            'paid_date' => $validated['paidDate'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];
    }
}
