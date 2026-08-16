<?php
declare(strict_types=1);

namespace phpbbseo\framework\Version;

/**
 * Authoritative runtime version identity for phpBB SEO Framework.
 */
final class Version
{
    public const VERSION = '1.0.5';
    public const EDITION = 'Lite';
    public const NAME = 'phpBB SEO Framework';
    public const REPOSITORY = 'phpbb-seo/seo-framework';
    public const HOMEPAGE = 'https://www.phpbbseo.com/';

    public static function getVersion(): string
    {
        return self::VERSION;
    }

    public static function getEdition(): string
    {
        return self::EDITION;
    }

    public static function getFullVersionString(): string
    {
        return 'v' . self::VERSION . ' • ' . self::EDITION . ' Edition';
    }
}
