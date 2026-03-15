<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
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
            'status' => ['sometimes', 'in:draft,sent,paid,overdue,cancelled'],
            'totalAmount' => ['sometimes', 'numeric', 'min:0', 'max:99999999.99'],
            'dueDate' => ['sometimes', 'date'],
            'paidDate' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
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
            'status' => 'Status',
            'totalAmount' => 'Gesamtbetrag',
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
        $data = [];

        if (isset($validated['status'])) {
            $data['status'] = $validated['status'];
        }
        if (isset($validated['totalAmount'])) {
            $data['total_amount'] = $validated['totalAmount'];
        }
        if (isset($validated['dueDate'])) {
            $data['due_date'] = $validated['dueDate'];
        }
        if (array_key_exists('paidDate', $validated)) {
            $data['paid_date'] = $validated['paidDate'];
        }
        if (array_key_exists('notes', $validated)) {
            $data['notes'] = $validated['notes'];
        }

        return $data;
    }
}
