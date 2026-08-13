<?php
declare(strict_types=1);

namespace phpbbseo\framework\Sitemap;

use phpbbseo\framework\Configuration\ConfigurationProvider;

/**
 * Builds the list of child sitemaps included in the root Sitemap Index.
 * Derives available topic sub-sitemaps directly from the boundary map.
 */
class SitemapIndexBuilder
{
    public function __construct(
        private readonly SitemapRepository $repository,
        private readonly SitemapUrlGenerator $urlGenerator,
        private readonly ConfigurationProvider $configProvider
    ) {
    }

    /**
     * @return array<int, array{loc: string, lastmod: ?string}>
     */
    public function buildIndex(): array
    {
        $boardUrl = $this->urlGenerator->getBoardUrl();
        $chunkSize = (int) $this->configProvider->get('seo_sitemap_urls_per_file', '50000');
        if ($chunkSize < 100) {
            $chunkSize = 50000;
        }

        $sitemaps = [];

        // 1. Pages Sitemap
        $sitemaps[] = [
            'loc'     => $boardUrl . 'sitemap-pages.xml',
            'lastmod' => null,
        ];

        // 2. Forums Sitemap
        $sitemaps[] = [
            'loc'     => $boardUrl . 'sitemap-forums.xml',
            'lastmod' => null,
        ];

        // 3. Chunked Topic Sitemaps derived strictly from the topic boundary map
        $boundaries = $this->repository->getTopicBoundaries($chunkSize);
        if (empty($boundaries)) {
            $sitemaps[] = [
                'loc'     => $boardUrl . 'sitemap-topics-1.xml',
                'lastmod' => null,
            ];
        } else {
            foreach (array_keys($boundaries) as $p) {
                $sitemaps[] = [
                    'loc'     => $boardUrl . 'sitemap-topics-' . $p . '.xml',
                    'lastmod' => null,
                ];
            }
        }

        return $sitemaps;
    }
}
