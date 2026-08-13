<?php
declare(strict_types=1);

namespace phpbbseo\framework\Metadata;

/**
 * Normalizes rich text (BBCode/HTML) into clean, raw UTF-8 plain text for metadata.
 * Note: Escaping happens at the template/HTML injection boundary, NOT inside normalizer.
 */
class PlainTextNormalizer
{
    /**
     * Cleans and normalizes text into raw plain text.
     *
     * @param string $text Raw BBCode or HTML content
     * @param int $maxLength Maximum character length (0 = no truncation)
     * @return string Clean raw UTF-8 plain text
     */
    public function normalize(string $text, int $maxLength = 155): string
    {
        if (trim($text) === '') {
            return '';
        }

        // 1. Remove quote blocks and code blocks with their contents if present
        $clean = preg_replace('#\[quote(?:="?[^"\]]*"?)?\][\s\S]*?\[/quote\]#ui', '', $text) ?? $text;
        $clean = preg_replace('#\[code(?:="?[^"\]]*"?)?\][\s\S]*?\[/code\]#ui', '', $clean) ?? $clean;
        $clean = preg_replace('#\[attachment=.*?\][\s\S]*?\[/attachment\]#ui', '', $clean) ?? $clean;

        // 2. Use phpBB native strip_bbcode if available, otherwise strip remaining BBCode tags
        if (function_exists('strip_bbcode')) {
            strip_bbcode($clean);
        }
        $clean = preg_replace('#\[/?[a-z0-9\*\+\-]+(?:=[^\]]*)?\]#ui', '', $clean) ?? $clean;

        // 3. Strip HTML tags
        $clean = strip_tags($clean);

        // 4. Decode HTML entities to clean raw Unicode
        $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 5. Normalize multiple whitespace and linebreaks to single spaces
        $clean = preg_replace('#[\r\n\t\s]+#u', ' ', $clean) ?? $clean;
        $clean = trim($clean);

        // 6. Multibyte-safe Unicode truncation at word boundary
        if ($maxLength > 0 && mb_strlen($clean, 'UTF-8') > $maxLength) {
            $truncated = mb_substr($clean, 0, $maxLength, 'UTF-8');
            // Try to break at the last space if reasonable
            $lastSpace = mb_strrpos($truncated, ' ', 0, 'UTF-8');
            if ($lastSpace !== false && $lastSpace > (int) ($maxLength * 0.7)) {
                $truncated = mb_substr($truncated, 0, $lastSpace, 'UTF-8');
            }
            $clean = rtrim($truncated, " \t\n\r\0\x0B.,:;!?-") . '...';
        }

        return $clean;
    }
}
