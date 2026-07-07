<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnamnesisTemplateRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'isDefault' => ['boolean'],
            'questions' => ['sometimes', 'array'],
            'questions.*.id' => ['sometimes', 'integer'],
            'questions.*.questionText' => ['required_with:questions', 'string'],
            'questions.*.questionType' => ['required_with:questions', 'string', 'in:text,textarea,select,multiselect,checkbox,radio,file'],
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
        $data = [];

        if (isset($validated['name'])) {
            $data['name'] = $validated['name'];
        }

        if (array_key_exists('description', $validated)) {
            $data['description'] = $validated['description'];
        }

        if (isset($validated['isDefault'])) {
            $data['is_default'] = $validated['isDefault'];
        }

        if (array_key_exists('questions', $validated)) {
            $data['questions'] = array_map(function ($question) {
                $mapped = [
                    'question_text' => $question['questionText'],
                    'question_type' => $question['questionType'],
                    'options' => $question['options'] ?? null,
                    'is_required' => $question['isRequired'] ?? false,
                    'order' => $question['order'] ?? 0,
                ];

                if (array_key_exists('id', $question)) {
                    $mapped['id'] = $question['id'];
                }

                return $mapped;
            }, $validated['questions']);
        }

        return $data;
    }
}
