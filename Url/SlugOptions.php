<?php
declare(strict_types=1);

namespace phpbbseo\framework\Url;

/**
 * Immutable value object holding rules for slug generation.
 */
class SlugOptions
{
    public function __construct(
        public readonly int $maxLength = 255,
        public readonly string $separator = '-',
        public readonly bool $lowercase = true,
        public readonly string $fallback = 'item'
    ) {}
}
