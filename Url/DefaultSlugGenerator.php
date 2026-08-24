<?php
declare(strict_types=1);

namespace phpbbseo\framework\Url;

class DefaultSlugGenerator implements SlugGeneratorInterface
{
    public function __construct(
        private readonly SlugOptions $options = new SlugOptions()
    ) {}

    public function generate(string $text): string
    {
        // 1. Ensure valid UTF-8
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        // 2. Decode HTML entities repeatedly to handle semantic entities and nested/double encodings (e.g. &amp;amp; -> &, &lt; -> <)
        $maxDecodeLoops = 3;
        while ($maxDecodeLoops-- > 0 && preg_match('/&[#a-zA-Z0-9]+;/u', $text)) {
            $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $text) {
                break;
            }
            $text = $decoded;
        }

        // 3. Strip HTML markup tags (e.g. <b>text</b> or decoded <tags>)
        $text = strip_tags($text);

        // 4. Normalize Unicode to Normalization Form C (or NFKC)
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if ($normalized !== false && $normalized !== null) {
                $text = $normalized;
            }
        }
        
        // 4. Lowercase
        if ($this->options->lowercase) {
            $text = mb_strtolower($text, 'UTF-8');
        }

        // 5. Replace unallowed characters (keep letters, marks, numbers from any language)
        // \p{L} = letters, \p{M} = marks, \p{N} = numbers
        $text = preg_replace('/[^\p{L}\p{M}\p{N}]+/u', $this->options->separator, $text) ?? '';

        // 5. Trim separators
        $separatorPreg = preg_quote($this->options->separator, '/');
        $text = preg_replace('/^' . $separatorPreg . '+|' . $separatorPreg . '+$/u', '', $text) ?? '';

        // 6. Truncate without splitting code points
        if (mb_strlen($text, 'UTF-8') > $this->options->maxLength) {
            $text = mb_substr($text, 0, $this->options->maxLength, 'UTF-8');
            // Trim again in case we cut at a separator
            $text = preg_replace('/' . $separatorPreg . '+$/u', '', $text) ?? '';
        }

        // 7. Fallback
        if ($text === '') {
            return $this->options->fallback;
        }

        return $text;
    }
}
