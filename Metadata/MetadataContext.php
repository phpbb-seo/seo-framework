<?php
declare(strict_types=1);

namespace phpbbseo\framework\Metadata;

/**
 * Immutable context object containing page and entity information for metadata resolution.
 */
class MetadataContext
{
    /**
     * @param string $resourceType 'home' | 'forum' | 'topic' | 'member' | 'other'
     * @param int $resourceId
     * @param array<string, mixed> $entityData
     * @param int $pageNumber
     * @param string $boardName
     * @param string $siteDesc
     */
    public function __construct(
        public readonly string $resourceType,
        public readonly int $resourceId,
        public readonly array $entityData,
        public readonly int $pageNumber,
        public readonly string $boardName,
        public readonly string $siteDesc
    ) {}
}
