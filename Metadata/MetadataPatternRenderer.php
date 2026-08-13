<?php
declare(strict_types=1);

namespace phpbbseo\framework\Metadata;

/**
 * Renders configurable title/description patterns with context-aware token replacement
 * and deterministic pagination collapse.
 */
class MetadataPatternRenderer
{
    /**
     * Renders a pattern into raw plain text using the given token replacements.
     *
     * @param string $pattern Configured pattern string
     * @param array<string, string|int> $tokens Key-value tokens (e.g. ['board_name' => 'My Board'])
     * @param int $pageNumber Current page number (1-based)
     * @param string $pageLabel Localized page label for page > 1 (e.g. "Page 2" or "صفحه ۲")
     * @return string Resolved raw UTF-8 string
     */
    public function render(string $pattern, array $tokens, int $pageNumber = 1, string $pageLabel = ''): string
    {
        $rendered = $pattern;

        // 1. Deterministic Pagination Handling
        if ($pageNumber <= 1) {
            // Collapse {page} and adjacent delimiters without leaving orphan separators or double spaces
            $rendered = preg_replace('#\s*[\-\|\—\–]\s*\{page\}#u', '', $rendered) ?? $rendered;
            $rendered = preg_replace('#\{page\}\s*[\-\|\—\–]\s*#u', '', $rendered) ?? $rendered;
            $rendered = preg_replace('#\[\{page\}\]\s*#u', '', $rendered) ?? $rendered;
            $rendered = preg_replace('#\(\{page\}\)\s*#u', '', $rendered) ?? $rendered;
            $rendered = str_replace('{page}', '', $rendered);
        } else {
            $replacement = $pageLabel !== '' ? $pageLabel : ('Page ' . $pageNumber);
            $rendered = str_replace('{page}', $replacement, $rendered);
        }

        // 2. Replace all assigned context tokens
        foreach ($tokens as $key => $value) {
            $tokenPlaceholder = '{' . trim((string) $key, '{}') . '}';
            $rendered = str_replace($tokenPlaceholder, (string) $value, $rendered);
        }

        // 3. Strip any remaining unassigned/unknown tokens safely
        $rendered = preg_replace('#\{[a-z0-9_\-]+\}#ui', '', $rendered) ?? $rendered;

        // 4. Clean up any trailing/leading or double separators created by empty tokens
        $rendered = preg_replace('#\s*([\-\|\—\–])\s*(\1\s*)+#u', ' $1 ', $rendered) ?? $rendered;
        $rendered = preg_replace('#^\s*[\-\|\—\–]\s*#u', '', $rendered) ?? $rendered;
        $rendered = preg_replace('#\s*[\-\|\—\–]\s*$#u', '', $rendered) ?? $rendered;

        // 5. Normalize whitespace
        $rendered = preg_replace('#\s+#u', ' ', $rendered) ?? $rendered;

        return trim($rendered);
    }
}
