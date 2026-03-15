<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Dog;
use Illuminate\Foundation\Http\FormRequest;

class StoreAnamnesisResponseRequest extends FormRequest
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
            'dogId' => ['required', 'integer', 'exists:dogs,id'],
            'templateId' => ['required', 'integer', 'exists:anamnesis_templates,id'],
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
            'dog_id' => $validated['dogId'],
            'template_id' => $validated['templateId'],
            'completed_by' => $this->user()->id,
            'answers' => isset($validated['answers']) ? array_map(function ($answer) {
                return [
                    'question_id' => $answer['questionId'],
                    'answer_value' => $answer['answerValue'] ?? null,
                ];
            }, $validated['answers']) : [],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                // Verify the user has access to the dog
                $dog = Dog::find($this->input('dogId'));
                if ($dog && !$this->user()->can('view', $dog)) {
                    $validator->errors()->add('dogId', 'You do not have access to this dog.');
                }
            }
        });
    }
}
