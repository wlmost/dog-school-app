<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVaccinationRequest extends FormRequest
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
            'vaccinationType' => ['sometimes', 'string', 'max:100'],
            'vaccinationDate' => ['sometimes', 'date', 'before_or_equal:today'],
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
        $data = [];

        if (isset($validated['vaccinationType'])) {
            $data['vaccination_type'] = $validated['vaccinationType'];
        }
        if (isset($validated['vaccinationDate'])) {
            $data['vaccination_date'] = $validated['vaccinationDate'];
        }
        if (array_key_exists('nextDueDate', $validated)) {
            $data['next_due_date'] = $validated['nextDueDate'];
        }
        if (array_key_exists('veterinarian', $validated)) {
            $data['veterinarian'] = $validated['veterinarian'];
        }
        if (array_key_exists('documentPath', $validated)) {
            $data['document_path'] = $validated['documentPath'];
        }

        return $data;
    }
}
