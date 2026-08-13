<?php
declare(strict_types=1);

namespace phpbbseo\framework\Rewrite;

/**
 * Lightweight value object representing a detected phpBB public resource target.
 */
class ResourceTarget
{
    /**
     * @param string $type The resource type ('forum', 'topic', 'member')
     * @param int $id The unique entity identifier
     * @param array<string, mixed> $paginationParams Associated pagination context (e.g. ['start' => 20])
     */
    public function __construct(
        private readonly string $type,
        private readonly int $id,
        private readonly array $paginationParams = []
    ) {}

    public function getType(): string
    {
        return $this->type;
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPaginationParams(): array
    {
        return $this->paginationParams;
    }
}
