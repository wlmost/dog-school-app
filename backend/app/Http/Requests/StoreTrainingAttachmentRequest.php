<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrainingAttachmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'training_log_id' => ['required', 'integer', 'exists:training_logs,id'],
            'trainingLogId' => ['required', 'integer', 'exists:training_logs,id'],
            'file' => [
                'required',
                'file',
                'max:51200', // 50MB max file size
                'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,pdf,doc,docx',
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert camelCase to snake_case
        if ($this->has('trainingLogId')) {
            $this->merge([
                'training_log_id' => $this->input('trainingLogId'),
            ]);
        }
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'training_log_id.required' => 'Training Log ID ist erforderlich.',
            'training_log_id.exists' => 'Das angegebene Training Log existiert nicht.',
            'file.required' => 'Eine Datei muss hochgeladen werden.',
            'file.file' => 'Die hochgeladene Datei ist ungültig.',
            'file.max' => 'Die Datei darf maximal 50MB groß sein.',
            'file.mimes' => 'Erlaubte Dateitypen: jpg, jpeg, png, gif, webp, mp4, mov, avi, pdf, doc, docx.',
        ];
    }
}
