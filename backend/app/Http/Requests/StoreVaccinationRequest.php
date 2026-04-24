<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVaccinationRequest extends FormRequest
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
            'dogId' => ['required', 'integer', 'exists:dogs,id'],
            'vaccinationType' => ['required', 'string', 'max:100'],
            'vaccinationDate' => ['required', 'date', 'before_or_equal:today'],
            'nextDueDate' => ['nullable', 'date', 'after:vaccinationDate'],
            'veterinarian' => ['nullable', 'string', 'max:255'],
            'documentPath' => ['nullable', 'string', 'max:500'],
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
            'dogId' => 'Hund',
            'vaccinationType' => 'Impftyp',
            'vaccinationDate' => 'Impfdatum',
            'nextDueDate' => 'Nächste Fälligkeit',
            'veterinarian' => 'Tierarzt',
            'documentPath' => 'Dokumentenpfad',
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
            'dog_id' => $validated['dogId'],
            'vaccination_type' => $validated['vaccinationType'],
            'vaccination_date' => $validated['vaccinationDate'],
            'next_due_date' => $validated['nextDueDate'] ?? null,
            'veterinarian' => $validated['veterinarian'] ?? null,
            'document_path' => $validated['documentPath'] ?? null,
        ];
    }
}
