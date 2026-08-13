<?php
declare(strict_types=1);

namespace phpbbseo\framework\Rewrite;

use phpbbseo\framework\Configuration\ConfigurationProvider;

/**
 * Loads and manages the active permalink preset configuration.
 *
 * NOTE ON TERMINOLOGY:
 * - Permalink Preset (modern / compact / classic / custom): controls URL appearance/structure.
 *   Changing the preset only changes the URL pattern; the same UrlPatternCompiler is always used.
 *
 * All presets use the identical UrlPatternCompiler and PermalinkRewriteProfile.
 * There is no separate URL generation implementation per preset.
 */
class PermalinkConfiguration
{
    private const DEFAULT_PRESET = 'modern';

    private const PRESETS = [
        'modern' => [
            'forum'      => '/forum/{slug}-{id}/',
            'forum_page' => '/forum/{slug}-{id}/page-{page}/',
            'topic'      => '/topic/{slug}-{id}/',
            'topic_page' => '/topic/{slug}-{id}/page-{page}/',
            'member'     => '/member/{slug}-{id}/',
            'group'      => '/group/{slug}-{id}/',
        ],
        'compact' => [
            'forum'      => '/f/{id}/{slug}/',
            'forum_page' => '/f/{id}/{slug}/p/{page}/',
            'topic'      => '/t/{id}/{slug}/',
            'topic_page' => '/t/{id}/{slug}/p/{page}/',
            'member'     => '/u/{id}/{slug}/',
            'group'      => '/g/{id}/{slug}/',
        ],
        'classic' => [
            'forum'      => '/forum-{id}/{slug}.html',
            'forum_page' => '/forum-{id}/{slug}-{page}.html',
            'topic'      => '/{slug}-t{id}.html',
            'topic_page' => '/{slug}-t{id}-{page}.html',
            'member'     => '/member-{id}/{slug}.html',
            'group'      => '/group-{id}/{slug}.html',
        ],
    ];

    public function __construct(
        private readonly ConfigurationProvider $configProvider
    ) {}

    /**
     * Returns the active permalink preset name (e.g. 'modern', 'compact', 'classic', 'custom').
     * Reads from the phpBB config key 'seo_permalink_preset'.
     * Falls back to 'modern' if unset or invalid.
     */
    public function getActivePreset(): string
    {
        $preset = $this->configProvider->get('seo_permalink_preset', self::DEFAULT_PRESET);
        return isset(self::PRESETS[$preset]) || $preset === 'custom'
            ? $preset
            : self::DEFAULT_PRESET;
    }

    /**
     * Returns the raw pattern string for the given resource type under the active preset.
     * For 'custom' preset, reads from per-resource config keys.
     * Falls back to the default 'modern' pattern if any configuration is missing or invalid.
     */
    public function getPattern(string $resource): string
    {
        $preset = $this->getActivePreset();

        if ($preset === 'custom') {
            $customPattern = $this->configProvider->get('seo_pattern_' . $resource, '');
            // Fall back to default preset if custom pattern is empty or unconfigured
            return $customPattern !== ''
                ? $customPattern
                : (self::PRESETS[self::DEFAULT_PRESET][$resource] ?? '');
        }

        $patterns = self::PRESETS[$preset] ?? self::PRESETS[self::DEFAULT_PRESET];
        return $patterns[$resource] ?? self::PRESETS[self::DEFAULT_PRESET][$resource] ?? '';
    }
}
