<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnamnesisTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'isDefault' => ['boolean'],
            'questions' => ['array'],
            'questions.*.questionText' => ['required', 'string'],
            'questions.*.questionType' => ['required', 'string', 'in:text,textarea,select,multiselect,checkbox,radio,file'],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.options.*' => ['string'],
            'questions.*.isRequired' => ['boolean'],
            'questions.*.order' => ['integer', 'min:0'],
        ];
    }

    /**
     * Get validated data with snake_case keys for database.
     *
     * @return array<string, mixed>
     */
    public function validatedSnakeCase(): array
    {
        $validated = $this->validated();

        return [
            'trainer_id' => $this->user()->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_default' => $validated['isDefault'] ?? false,
            'questions' => isset($validated['questions']) ? array_map(function ($question) {
                return [
                    'question_text' => $question['questionText'],
                    'question_type' => $question['questionType'],
                    'options' => $question['options'] ?? null,
                    'is_required' => $question['isRequired'] ?? false,
                    'order' => $question['order'] ?? 0,
                ];
            }, $validated['questions']) : [],
        ];
    }
}
