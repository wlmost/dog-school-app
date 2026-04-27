<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Provides server-side HTML sanitization for rich-text description fields.
 *
 * Strips any tags not in the safe allowlist and removes all HTML attributes
 * from allowed tags to eliminate event-handler and javascript: attack vectors.
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
     *
     * Strategy (defense in depth):
     * 1. strip_tags() removes all non-whitelisted tags.
     * 2. A regex then strips ALL HTML attributes from the remaining
     *    whitelisted tags, eliminating event-handler and href/src vectors.
     *    None of the allowed tags require attributes for the editor output.
     */
    protected function sanitizeHtmlDescription(string $html): string
    {
        // 1. Remove all tags not in the safe allowlist
        $sanitized = strip_tags($html, self::ALLOWED_HTML_TAGS);

        // 2. Strip ALL attributes from the remaining allowed tags.
        //    This closes the strip_tags() loophole where attributes of
        //    allowed tags (e.g. onclick="…" on <strong>) are preserved.
        $sanitized = (string) preg_replace('/<(\w+)(?:\s[^>]*)?>/', '<$1>', $sanitized);

        return $sanitized;
    }
}
