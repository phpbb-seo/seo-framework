<?php
declare(strict_types=1);

namespace phpbbseo\framework\Controller;

use phpbbseo\framework\Sitemap\SitemapRepository;
use phpbbseo\framework\Sitemap\SitemapUrlGenerator;
use phpbbseo\framework\Sitemap\SitemapIndexBuilder;
use phpbbseo\framework\Configuration\ConfigurationProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller serving dynamic XML sitemaps and XSL styling via Symfony routing.
 * Streams XML safely with bounded memory usage, strict UTF-8 escaping, and visual XSL styling.
 */
class SitemapController
{
    public function __construct(
        private readonly SitemapRepository $repository,
        private readonly SitemapUrlGenerator $urlGenerator,
        private readonly SitemapIndexBuilder $indexBuilder,
        private readonly ConfigurationProvider $configProvider
    ) {
    }

    private function isSitemapEnabled(): bool
    {
        return $this->configProvider->isEnabled()
            && $this->configProvider->get('seo_sitemap_enable', '1') === '1';
    }

    private function createNotFoundResponse(): Response
    {
        return new Response('Sitemap is disabled or does not exist.', Response::HTTP_NOT_FOUND, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    /**
     * Root Sitemap Index: /sitemap.xml
     */
    public function indexAction(): Response
    {
        if (!$this->isSitemapEnabled()) {
            return $this->createNotFoundResponse();
        }

        $sitemaps = $this->indexBuilder->buildIndex();
        $xslUrl = $this->urlGenerator->getXslUrl();

        $response = new StreamedResponse(function () use ($sitemaps, $xslUrl) {
            echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
            echo "<?xml-stylesheet type=\"text/xsl\" href=\"" . htmlspecialchars($xslUrl, ENT_QUOTES | ENT_XML1, 'UTF-8') . "\"?>\n";
            echo "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

            foreach ($sitemaps as $sitemap) {
                echo "  <sitemap>\n";
                echo '    <loc>' . htmlspecialchars($sitemap['loc'], ENT_QUOTES | ENT_XML1, 'UTF-8') . "</loc>\n";
                if (!empty($sitemap['lastmod'])) {
                    echo '    <lastmod>' . htmlspecialchars($sitemap['lastmod'], ENT_QUOTES | ENT_XML1, 'UTF-8') . "</lastmod>\n";
                }
                echo "  </sitemap>\n";
            }

            echo "</sitemapindex>\n";
        });

        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    /**
     * Pages Sitemap: /sitemap-pages.xml
     */
    public function pagesAction(): Response
    {
        if (!$this->isSitemapEnabled()) {
            return $this->createNotFoundResponse();
        }

        $boardUrl = $this->urlGenerator->getBoardUrl();
        $xslUrl = $this->urlGenerator->getXslUrl();

        $response = new StreamedResponse(function () use ($boardUrl, $xslUrl) {
            echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
            echo "<?xml-stylesheet type=\"text/xsl\" href=\"" . htmlspecialchars($xslUrl, ENT_QUOTES | ENT_XML1, 'UTF-8') . "\"?>\n";
            echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
            echo "  <url>\n";
            echo '    <loc>' . htmlspecialchars($boardUrl, ENT_QUOTES | ENT_XML1, 'UTF-8') . "</loc>\n";
            echo "  </url>\n";
            echo "</urlset>\n";
        });

        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    /**
     * Forums Sitemap: /sitemap-forums.xml
     */
    public function forumsAction(): Response
    {
        if (!$this->isSitemapEnabled()) {
            return $this->createNotFoundResponse();
        }

        $forums = $this->repository->getPublicForums();
        $xslUrl = $this->urlGenerator->getXslUrl();

        $response = new StreamedResponse(function () use ($forums, $xslUrl) {
            echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
            echo "<?xml-stylesheet type=\"text/xsl\" href=\"" . htmlspecialchars($xslUrl, ENT_QUOTES | ENT_XML1, 'UTF-8') . "\"?>\n";
            echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

            foreach ($forums as $forum) {
                $url = $this->urlGenerator->generateForumUrl($forum['forum_id'], $forum['slug']);
                echo "  <url>\n";
                echo '    <loc>' . htmlspecialchars($url, ENT_QUOTES | ENT_XML1, 'UTF-8') . "</loc>\n";
                if ($forum['lastmod'] > 0) {
                    echo '    <lastmod>' . gmdate('Y-m-d\TH:i:s\Z', $forum['lastmod']) . "</lastmod>\n";
                }
                echo "  </url>\n";
            }

            echo "</urlset>\n";
        });

        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    /**
     * Chunked Topics Sitemap: /sitemap-topics-{page}.xml
     */
    public function topicsAction(int $page): Response
    {
        if (!$this->isSitemapEnabled() || $page < 1) {
            return $this->createNotFoundResponse();
        }

        $chunkSize = (int) $this->configProvider->get('seo_sitemap_urls_per_file', '50000');
        if ($chunkSize < 100) {
            $chunkSize = 50000;
        }

        $boundaries = $this->repository->getTopicBoundaries($chunkSize);
        if (!isset($boundaries[$page])) {
            return $this->createNotFoundResponse();
        }

        $repository = $this->repository;
        $urlGenerator = $this->urlGenerator;
        $xslUrl = $this->urlGenerator->getXslUrl();

        $response = new StreamedResponse(function () use ($repository, $urlGenerator, $xslUrl, $page, $chunkSize) {
            echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
            echo "<?xml-stylesheet type=\"text/xsl\" href=\"" . htmlspecialchars($xslUrl, ENT_QUOTES | ENT_XML1, 'UTF-8') . "\"?>\n";
            echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

            $repository->streamTopics($page, $chunkSize, function (int $topicId, string $slug, int $lastmod) use ($urlGenerator) {
                $url = $urlGenerator->generateTopicUrl($topicId, $slug);
                echo "  <url>\n";
                echo '    <loc>' . htmlspecialchars($url, ENT_QUOTES | ENT_XML1, 'UTF-8') . "</loc>\n";
                if ($lastmod > 0) {
                    echo '    <lastmod>' . gmdate('Y-m-d\TH:i:s\Z', $lastmod) . "</lastmod>\n";
                }
                echo "  </url>\n";
            });

            echo "</urlset>\n";
        });

        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    /**
     * Serve Modern Human-Readable XSLT Stylesheet: /sitemap.xsl
     */
    public function xslAction(): Response
    {
        $xsl = <<<'XSL'
<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
	xmlns:html="http://www.w3.org/TR/REC-html40"
	xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes" />
	<xsl:template match="/">
		<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
			<head>
				<title>XML Sitemap | phpBB SEO Framework</title>
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
				<meta name="viewport" content="width=device-width, initial-scale=1.0" />
				<style type="text/css">
					:root {
						--color-bg: #f8fafc;
						--color-surface: #ffffff;
						--color-border: #e2e8f0;
						--color-text-main: #0f172a;
						--color-text-muted: #64748b;
						--color-primary: #2563eb;
						--color-primary-hover: #1d4ed8;
						--color-accent: #0284c7;
						--color-pill-bg: #eff6ff;
						--color-pill-text: #1d4ed8;
						--shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
						--shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
						--radius: 10px;
					}
					* {
						box-sizing: border-box;
						margin: 0;
						padding: 0;
					}
					body {
						font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
						background-color: var(--color-bg);
						color: var(--color-text-main);
						font-size: 14px;
						line-height: 1.6;
						padding: 32px 16px;
					}
					.container {
						max-width: 1080px;
						margin: 0 auto;
					}
					.header-card {
						background: var(--color-surface);
						border: 1px solid var(--color-border);
						border-radius: var(--radius);
						padding: 24px 28px;
						box-shadow: var(--shadow-sm);
						margin-bottom: 24px;
					}
					.header-top {
						display: flex;
						align-items: center;
						justify-content: space-between;
						gap: 16px;
						flex-wrap: wrap;
						margin-bottom: 12px;
					}
					.header-title {
						font-size: 22px;
						font-weight: 700;
						color: var(--color-text-main);
						letter-spacing: -0.02em;
						display: flex;
						align-items: center;
						gap: 10px;
					}
					.badge {
						display: inline-flex;
						align-items: center;
						padding: 3px 10px;
						font-size: 12px;
						font-weight: 600;
						border-radius: 999px;
						background: var(--color-pill-bg);
						color: var(--color-pill-text);
					}
					.header-desc {
						font-size: 14px;
						color: var(--color-text-muted);
						margin-bottom: 14px;
					}
					.header-info {
						display: flex;
						align-items: center;
						gap: 12px;
						flex-wrap: wrap;
						font-size: 13px;
						color: var(--color-text-muted);
						padding-top: 12px;
						border-top: 1px solid var(--color-border);
					}
					.table-card {
						background: var(--color-surface);
						border: 1px solid var(--color-border);
						border-radius: var(--radius);
						overflow: hidden;
						box-shadow: var(--shadow-sm);
					}
					table {
						width: 100%;
						border-collapse: collapse;
						text-align: left;
					}
					th {
						background: #f1f5f9;
						color: #475569;
						font-size: 12px;
						font-weight: 600;
						text-transform: uppercase;
						letter-spacing: 0.05em;
						padding: 12px 18px;
						border-bottom: 1px solid var(--color-border);
					}
					td {
						padding: 12px 18px;
						border-bottom: 1px solid var(--color-border);
						color: var(--color-text-main);
						font-size: 13.5px;
						vertical-align: middle;
					}
					tr:last-child td {
						border-bottom: none;
					}
					tr:hover td {
						background-color: #f8fafc;
					}
					.url-link {
						color: var(--color-primary);
						text-decoration: none;
						word-break: break-all;
						font-weight: 500;
						transition: color 0.15s ease;
					}
					.url-link:hover {
						color: var(--color-primary-hover);
						text-decoration: underline;
					}
					.num-col {
						width: 60px;
						color: var(--color-text-muted);
						font-size: 12px;
						text-align: center;
					}
					.date-col {
						width: 220px;
						color: var(--color-text-muted);
						font-size: 13px;
						font-variant-numeric: tabular-nums;
						white-space: nowrap;
					}
					.footer {
						margin-top: 24px;
						text-align: center;
						font-size: 12px;
						color: var(--color-text-muted);
					}
				</style>
			</head>
			<body>
				<div class="container">
					<div class="header-card">
						<div class="header-top">
							<h1 class="header-title">
								XML Sitemap
								<span class="badge">phpBB SEO Framework</span>
							</h1>
							<xsl:if test="sitemap:sitemapindex">
								<span class="badge">
									<xsl:value-of select="count(sitemap:sitemapindex/sitemap:sitemap)"/> Sub-Sitemaps
								</span>
							</xsl:if>
							<xsl:if test="sitemap:urlset">
								<span class="badge">
									<xsl:value-of select="count(sitemap:urlset/sitemap:url)"/> Indexed URLs
								</span>
							</xsl:if>
						</div>
						<p class="header-desc">
							This XML Sitemap is generated dynamically by <strong>phpBB SEO Framework</strong> to provide search engines like Google, Bing, and Yandex with an authoritative index of all public community content.
						</p>
						<div class="header-info">
							<span>Generated for search engines</span>
							<span>•</span>
							<span>Standards Compliant (Sitemaps.org 0.9)</span>
						</div>
					</div>

					<div class="table-card">
						<xsl:if test="sitemap:sitemapindex">
							<table>
								<thead>
									<tr>
										<th class="num-col">#</th>
										<th>Sitemap URL</th>
										<th class="date-col">Last Modified (UTC)</th>
									</tr>
								</thead>
								<tbody>
									<xsl:for-each select="sitemap:sitemapindex/sitemap:sitemap">
										<tr>
											<td class="num-col"><xsl:value-of select="position()"/></td>
											<td>
												<a class="url-link" href="{sitemap:loc}">
													<xsl:value-of select="sitemap:loc"/>
												</a>
											</td>
											<td class="date-col">
												<xsl:choose>
													<xsl:when test="sitemap:lastmod">
														<xsl:value-of select="sitemap:lastmod"/>
													</xsl:when>
													<xsl:otherwise>-</xsl:otherwise>
												</xsl:choose>
											</td>
										</tr>
									</xsl:for-each>
								</tbody>
							</table>
						</xsl:if>

						<xsl:if test="sitemap:urlset">
							<table>
								<thead>
									<tr>
										<th class="num-col">#</th>
										<th>Public URL</th>
										<th class="date-col">Last Modified (UTC)</th>
									</tr>
								</thead>
								<tbody>
									<xsl:for-each select="sitemap:urlset/sitemap:url">
										<tr>
											<td class="num-col"><xsl:value-of select="position()"/></td>
											<td>
												<a class="url-link" href="{sitemap:loc}" target="_blank" rel="noopener noreferrer">
													<xsl:value-of select="sitemap:loc"/>
												</a>
											</td>
											<td class="date-col">
												<xsl:choose>
													<xsl:when test="sitemap:lastmod">
														<xsl:value-of select="sitemap:lastmod"/>
													</xsl:when>
													<xsl:otherwise>-</xsl:otherwise>
												</xsl:choose>
											</td>
										</tr>
									</xsl:for-each>
								</tbody>
							</table>
						</xsl:if>
					</div>

					<div class="footer">
						phpBB SEO Framework — High-Performance Search Engine Optimization
					</div>
				</div>
			</body>
		</html>
	</xsl:template>
</xsl:stylesheet>
XSL;

        $response = new Response($xsl, Response::HTTP_OK, [
            'Content-Type' => 'text/xsl; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
        ]);

        return $response;
    }
}
