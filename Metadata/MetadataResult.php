<?php
declare(strict_types=1);

namespace phpbbseo\framework\Metadata;

/**
 * Value object holding resolved plain UTF-8 title and description.
 * Notice: Values contain raw Unicode plain text (escaping happens at output boundary).
 */
class MetadataResult
{
    public function __construct(
        public readonly string $title,
        public readonly string $description = ''
    ) {}

    public function hasDescription(): bool
    {
        return trim($this->description) !== '';
    }
}
