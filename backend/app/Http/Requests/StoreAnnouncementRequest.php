<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesHtmlContent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Store Announcement Request
 *
 * Validates incoming requests to create a new announcement.
 */
class StoreAnnouncementRequest extends FormRequest
{
    use SanitizesHtmlContent;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only admins can create announcements
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'displayDays' => ['required', 'integer', 'min:1', 'max:365'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
        ];
    }

    /**
     * Get validated data with snake_case keys for database.
     *
     * The image upload is intentionally excluded here — the controller
     * handles file storage separately and sets image_path itself.
     *
     * @return array<string, mixed>
     */
    public function validatedSnakeCase(): array
    {
        $validated = $this->validated();

        return [
            'title' => $validated['title'],
            'body' => $this->sanitizeHtmlDescription($validated['body']),
            'display_days' => $validated['displayDays'],
        ];
    }
}
