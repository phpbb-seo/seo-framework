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

        $clean = $text;

        // Check if content is s9e TextFormatter XML (<r>...</r>, <t>...</t>, <m>...</m>)
        $isXml = str_starts_with($clean, '<r') || str_starts_with($clean, '<t') || str_starts_with($clean, '<m');

        if ($isXml) {
            // Strip quote, code, and attachment XML blocks with their inner text
            $clean = preg_replace('#<QUOTE[\s\S]*?</QUOTE>#ui', '', $clean) ?? $clean;
            $clean = preg_replace('#<CODE[\s\S]*?</CODE>#ui', '', $clean) ?? $clean;
            $clean = preg_replace('#<ATTACHMENT[\s\S]*?</ATTACHMENT>#ui', '', $clean) ?? $clean;
            $clean = preg_replace('#<e>[^<]*</e>#ui', '', $clean) ?? $clean;
            $clean = preg_replace('#<s>[^<]*</s>#ui', '', $clean) ?? $clean;
        } else {
            // Strip raw BBCode quote, code, and attachment blocks
            $clean = preg_replace('#\[quote(?:="?[^"\]]*"?)?\][\s\S]*?\[/quote\]#ui', '', $clean) ?? $clean;
            $clean = preg_replace('#\[code(?:="?[^"\]]*"?)?\][\s\S]*?\[/code\]#ui', '', $clean) ?? $clean;
            $clean = preg_replace('#\[attachment=.*?\][\s\S]*?\[/attachment\]#ui', '', $clean) ?? $clean;
            $clean = preg_replace('#\[/?[a-z0-9\*\+\-]+(?:=[^\]]*)?\]#ui', '', $clean) ?? $clean;
        }

        // Strip HTML / XML tags
        $clean = strip_tags($clean);

        // Decode HTML entities to clean raw Unicode
        $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize multiple whitespace and linebreaks to single spaces
        $clean = preg_replace('#[\r\n\t\s]+#u', ' ', $clean) ?? $clean;
        $clean = trim($clean);

        // Multibyte-safe Unicode truncation at word boundary
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
