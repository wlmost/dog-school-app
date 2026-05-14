<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Store Pricing Item Request
 *
 * Validates input for creating a new PricingItem.
 */
class StorePricingItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category'     => ['required', 'string', 'max:100'],
            'title'        => ['required', 'string', 'max:200'],
            'price'        => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'unit'         => ['nullable', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:500'],
            'isFromPrice'  => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the validated data mapped to snake_case keys.
     *
     * @return array<string, mixed>
     */
    public function validatedSnakeCase(): array
    {
        $validated = $this->validated();

        return [
            'category'      => $validated['category'],
            'title'         => $validated['title'],
            'price'         => $validated['price'],
            'unit'          => $validated['unit'] ?? null,
            'description'   => $validated['description'] ?? null,
            'is_from_price' => $validated['isFromPrice'] ?? false,
        ];
    }
}
