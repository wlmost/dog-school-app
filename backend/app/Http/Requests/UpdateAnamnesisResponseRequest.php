<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnamnesisResponseRequest extends FormRequest
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
            'answers' => ['array'],
            'answers.*.questionId' => ['required', 'integer', 'exists:anamnesis_questions,id'],
            'answers.*.answerValue' => ['nullable', 'string'],
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
            'answers' => isset($validated['answers']) ? array_map(function ($answer) {
                return [
                    'question_id' => $answer['questionId'],
                    'answer_value' => $answer['answerValue'] ?? null,
                ];
            }, $validated['answers']) : [],
        ];
    }
}
