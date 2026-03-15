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
            'issueDate' => ['required', 'date'],
            'dueDate' => ['required', 'date', 'after_or_equal:issueDate'],
            'status' => ['sometimes', 'in:draft,sent,paid,overdue,cancelled'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unitPrice' => ['required', 'numeric', 'min:0'],
            'items.*.taxRate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
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
            'issueDate' => 'Rechnungsdatum',
            'dueDate' => 'Fälligkeitsdatum',
            'status' => 'Status',
            'notes' => 'Notizen',
            'items' => 'Rechnungspositionen',
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
        
        // Calculate totals from items
        $subtotal = 0;
        $taxAmount = 0;
        foreach ($validated['items'] as $item) {
            $itemAmount = $item['quantity'] * $item['unitPrice'];
            $subtotal += $itemAmount;
            $taxRate = $item['taxRate'] ?? 19; // Default 19% MwSt
            $taxAmount += $itemAmount * ($taxRate / 100);
        }
        $totalAmount = $subtotal + $taxAmount;
        
        // Generate invoice number if not provided
        $invoiceNumber = $this->generateInvoiceNumber();
        
        return [
            'customer_id' => $validated['customerId'],
            'invoice_number' => $invoiceNumber,
            'issue_date' => $validated['issueDate'],
            'due_date' => $validated['dueDate'],
            'status' => $validated['status'] ?? 'draft',
            'subtotal_amount' => round($subtotal, 2),
            'tax_amount' => round($taxAmount, 2),
            'total_amount' => round($totalAmount, 2),
            'notes' => $validated['notes'] ?? null,
        ];
    }
    
    /**
     * Generate a unique invoice number.
     *
     * @return string
     */
    private function generateInvoiceNumber(): string
    {
        $year = date('Y');
        $lastInvoice = \App\Models\Invoice::where('invoice_number', 'like', "RE-{$year}-%")
            ->orderBy('invoice_number', 'desc')
            ->first();
            
        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->invoice_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return sprintf('RE-%s-%04d', $year, $newNumber);
    }
}
