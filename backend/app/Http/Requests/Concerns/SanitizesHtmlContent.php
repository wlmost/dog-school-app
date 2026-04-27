<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Provides server-side HTML sanitization for rich-text description fields.
 *
 * Strips any tags not in the safe allowlist and removes dangerous attributes
 * such as event handlers and javascript: protocol URIs.
 */
trait SanitizesHtmlContent
{
    /**
     * Tags that the Tiptap rich-text editor is allowed to produce.
     * This list is intentionally broader than the visible toolbar to also
     * cover content typed via keyboard shortcuts (e.g. blockquote, code).
     *
     * @var list<string>
     */
    private const ALLOWED_HTML_TAGS = [
        'p', 'br', 'strong', 'em',
        'h2', 'h3',
        'ul', 'ol', 'li',
        'blockquote', 'code', 'pre',
    ];

    /**
     * Strip dangerous HTML tags and attributes from a description string.
     * Allows only the safe formatting tags produced by the Tiptap editor.
     */
    protected function sanitizeHtmlDescription(string $html): string
    {
        // Remove all tags not in the safe allowlist
        $sanitized = strip_tags($html, self::ALLOWED_HTML_TAGS);

        // Remove event handler attributes (e.g. onclick="…", onmouseover='…')
        $sanitized = (string) preg_replace(
            '/\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]*)/i',
            '',
            $sanitized
        );

        // Remove javascript: protocol from any remaining attribute values
        $sanitized = (string) preg_replace('/javascript\s*:/i', '', $sanitized);

        return $sanitized;
    }
}
