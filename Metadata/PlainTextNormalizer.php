<?php
declare(strict_types=1);

namespace phpbbseo\framework\Metadata;

/**
 * Normalizes rich text (s9e XML/BBCode/HTML) into clean, raw UTF-8 plain text for metadata.
 * Note: Escaping happens at the template/HTML injection boundary, NOT inside normalizer.
 */
class PlainTextNormalizer
{
    /**
     * Cleans and normalizes text into raw plain text.
     *
     * @param string $text Raw BBCode, s9e XML, or HTML content
     * @param int $maxLength Maximum character length (0 = no truncation)
     * @return string Clean raw UTF-8 plain text
     */
    public function normalize(string $text, int $maxLength = 155): string
    {
        if (trim($text) === '') {
            return '';
        }

        $clean = $text;

        // 1. Check if content is s9e TextFormatter XML (<r>...</r>, <t>...</t>, <m>...</m>)
        $isXml = str_starts_with($clean, '<r') || str_starts_with($clean, '<t') || str_starts_with($clean, '<m');

        if ($isXml) {
            // Strip quote, code, and attachment XML blocks with their inner text
            $clean = preg_replace('#<QUOTE[\s\S]*?</QUOTE>#ui', '', $clean) ?? $clean;
            $clean = preg_replace('#<CODE[\s\S]*?</CODE>#ui', '', $clean) ?? $clean;
            $clean = preg_replace('#<ATTACHMENT[\s\S]*?</ATTACHMENT>#ui', '', $clean) ?? $clean;
            $clean = preg_replace('#<e>[^<]*</e>#ui', '', $clean) ?? $clean;
            $clean = preg_replace('#<s>[^<]*</s>#ui', '', $clean) ?? $clean;

            // Unparse s9e XML if class is available
            if (class_exists(\s9e\TextFormatter\Unparser::class)) {
                try {
                    $clean = \s9e\TextFormatter\Unparser::unparse($clean);
                } catch (\Throwable) {
                    // Fallback to regex pipeline
                }
            }
        }

        // 2. Strip quote, code, and attachment blocks in raw / legacy / UID-tagged BBCode
        $clean = preg_replace('#\[quote(?::[a-z0-9]+)?(?:="?[^"\]]*"?)?\][\s\S]*?\[/quote(?::[a-z0-9]+)?\]#ui', ' ', $clean) ?? $clean;
        $clean = preg_replace('#\[code(?::[a-z0-9]+)?(?:="?[^"\]]*"?)?\][\s\S]*?\[/code(?::[a-z0-9]+)?\]#ui', ' ', $clean) ?? $clean;
        $clean = preg_replace('#\[attachment(?::[a-z0-9]+)?=.*?\][\s\S]*?\[/attachment(?::[a-z0-9]+)?\]#ui', ' ', $clean) ?? $clean;

        // 3. Replace list items and block breaks with a space to prevent glued words
        $clean = preg_replace('#\[\*(?::[a-z0-9]+)?\]#ui', ' ', $clean) ?? $clean;
        $clean = preg_replace('#\[/\*(?::[a-z0-9]+)?\]#ui', ' ', $clean) ?? $clean;

        // 4. Strip all remaining opening/closing BBCode tags (custom, standard, UID-tagged, nested)
        $clean = preg_replace('#\[/?[a-z0-9_\-\*\+\:]+(?:=[^\]]*)?\]#ui', '', $clean) ?? $clean;

        // 5. Strip remaining HTML / XML tags
        $clean = strip_tags($clean);

        // 6. Decode HTML entities to clean raw Unicode
        $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 7. Normalize multiple whitespace and linebreaks to single spaces
        $clean = preg_replace('#[\r\n\t\s]+#u', ' ', $clean) ?? $clean;
        $clean = trim($clean);

        // 8. Multibyte-safe Unicode truncation at word boundary AFTER cleanup
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
