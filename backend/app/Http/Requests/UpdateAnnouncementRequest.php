<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesHtmlContent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Update Announcement Request
 *
 * Validates incoming requests to update an existing announcement.
 * All fields are optional to allow partial updates.
 */
class UpdateAnnouncementRequest extends FormRequest
{
    use SanitizesHtmlContent;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only admins can update announcements
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
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string', 'max:5000'],
            'displayDays' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
        ];
    }

    /**
     * Get validated data with snake_case keys for database.
     *
     * Only fields actually present in the request are returned, so
     * untouched fields are left unchanged by the caller's update() call.
     * The image upload is intentionally excluded here — the controller
     * handles file storage separately and sets image_path itself.
     *
     * @return array<string, mixed>
     */
    public function validatedSnakeCase(): array
    {
        $validated = $this->validated();
        $result = [];

        if (array_key_exists('title', $validated)) {
            $result['title'] = $validated['title'];
        }

        if (array_key_exists('body', $validated)) {
            $result['body'] = $this->sanitizeHtmlDescription($validated['body']);
        }

        if (array_key_exists('displayDays', $validated)) {
            $result['display_days'] = $validated['displayDays'];
        }

        return $result;
    }
}
