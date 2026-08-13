<?php
declare(strict_types=1);

namespace phpbbseo\framework\Rewrite;

/**
 * Handles numeric-to-string mappings for resource types stored in the database.
 */
class ResourceType
{
    public const FORUM = 1;
    public const TOPIC = 2;
    public const MEMBER = 3;
    public const GROUP = 4;

    private const STR_TO_INT = [
        'forum'  => self::FORUM,
        'topic'  => self::TOPIC,
        'member' => self::MEMBER,
        'group'  => self::GROUP,
    ];

    public static function fromString(string $type): int
    {
        return self::STR_TO_INT[$type] ?? 0;
    }

    public static function toString(int $type): string
    {
        static $intToStr = null;
        if ($intToStr === null) {
            $intToStr = array_flip(self::STR_TO_INT);
        }
        return $intToStr[$type] ?? '';
    }
}
