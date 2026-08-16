# Changelog

All notable changes to the **phpBB SEO Framework** project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.5] - 2026-08-16

### Added
* **Authoritative ACP Version Identity & Update Checker**:
  * Added authoritative framework runtime version source (`phpbbseo\framework\Version\Version`) preventing version duplication across ACP modules.
  * Added lightweight, cached GitHub Releases update checker service (`phpbbseo\framework\Update\UpdateChecker`) querying official releases (`phpbb-seo/seo-framework`).
  * Added ACP dashboard notification banners displaying real-time version status (`up to date`, `update available`, `development build`, `unavailable`).
  * Added manual update check and direct official release/download asset links with zero frontend overhead.
* **Branded SEO Source Organization & Head Markup**:
  * Added clean, branded HTML comment markers around SEO metadata (`<!-- Search Engine Optimization by phpBB SEO Framework - https://www.phpbbseo.com/ -->` ... `<!-- /phpBB SEO Framework -->`).
  * Unified `<title>`, `<meta name="description">`, and `<link rel="canonical">` into a single, clean block in the `<head>` section while eliminating duplicate tags.

### Fixed
* **Titles & Meta HTML Double-Escaping**:
  * Fixed double-escaping (`&amp;amp;`) in `<title>` tags by normalizing input tokens (`forum_name`, `topic_title`, `username`, `board_name`, `site_desc`) into raw plain semantic text internally, guaranteeing single HTML escaping strictly at the output boundary.
* **Automatic Meta Description BBCode & Markup Cleanup**:
  * Fixed topic auto-generated meta descriptions leaking raw BBCode tags (`[center]`, `[b]`, `[i]`, `[url]`, `[quote]`, `[code]`, etc.).
  * Implemented a comprehensive plain text cleanup pipeline with `s9e\TextFormatter` unparsing, removal of quote/code/attachment blocks, stripping of custom/UID-tagged BBCodes, and word-boundary multibyte Unicode truncation.
* **ACP Version Display**:
  * Fixed hardcoded `v1.0.1` version strings in ACP header and footer templates to dynamically reflect the authoritative framework version.
* **Operational & AJAX Endpoint Resolution from Nested SEO URLs**:
  * Established board-root URL context (`PHPBB_USE_BOARD_URL_PATH`) during framework bootstrap (`core.common`), ensuring native phpBB operational endpoints (`mcp.php`, `posting.php`, `ucp.php`, `report.php`, `download/file.php`) generate fully-rooted URLs.
  * Fixed Quick Moderation AJAX actions (`lock`, `unlock`, `make_sticky`, `make_announce`, `make_global`, `make_normal`, `delete_topic`, `restore_topic`, `move`, `fork`) failing with `404 Not Found` ("The requested page could not be found") when invoked from nested topic pages.
  * Prevented client-side relative internal phpBB URLs from incorrectly resolving below `/topic/`, `/forum/`, `/member/`, or `/group/` URL structures.

---

## [1.0.4] - 2026-08-16

### Added
* **Public Batch Preload API**:
  * Added `EntitySeoContext::preloadTopics(array $topicIds)` and `EntitySeoContext::preloadPosts(array $postIds)` allowing heavy custom extensions and widgets to batch-load metadata in a single query.

### Fixed
* **Third-Party `append_sid()` Extension Compatibility**:
  * Fixed `ResourceDetector` prioritizing `p` over `t` on combined `viewtopic.php?t=X&p=Y#pY` links, ensuring explicit `t` is authoritative with zero queries to `phpbb_posts`.
  * Added request-scoped fallback discovery and negative caching for isolated `p-only` links (`viewtopic.php?p=Y`) without N+1 query loops.
  * Preserved `#pXXX` post anchors seamlessly during outbound SEO topic URL rewriting.
  * Stripped consumed `p` query parameters from rewritten clean URLs.

---

## [1.0.3] - 2026-08-16

### Fixed
* **Multi-Page Topic & Forum Pagination**:
  * Fixed inbound pagination navigation returning page 1 or 404 on multi-page forum and topic SEO URLs by resolving the `$start` offset early inside `core.common` before phpBB controller variable initialization.
  * Added bidirectional support and routing aliases for both `/page/{page}/` and `/page-{page}/` pagination permalink patterns.
  * Subscribed to `core.pagination_generate_page_link` to cleanly rewrite all template page number links into friendly SEO URLs directly in HTML output.
  * Fixed trailing slash regex duplication in `RouteCacheCompiler` that prevented matching route patterns ending with a slash.
  * Normalized relative and board-prefixed URLs in `PublicResourceUrlResolver` during pagination link resolution.

### Changed
* **Generic Documentation**:
  * Cleaned up hardcoded version numbers in `README.md` Overview and feature comparison tables.

---

## [1.0.2] - 2026-08-15

### Fixed
* **Inbound Route Lifecycle Resilience**:
  * Fixed inbound SEO URLs returning 404 after extension disable/re-enable or delete data/reinstall cycles.
  * Added automatic recovery and persistence of missing compiled route cache on first request without requiring manual ACP intervention.
  * Improved extension lifecycle reliability with fail-safe route dumping in `enable_step` and clean disabled markers in `disable_step`.
* **BBCode & TextFormatter Sanitization**:
  * Fixed `s9e/text-formatter` XML warnings (`DOMDocument::loadXML() tag mismatch` and `textContent on null`) during BBCode posting and previewing by safely parsing XML structures separately from raw BBCode.

### Added
* **Official Nginx Support**:
  * Added and verified minimal Nginx `try_files` configuration snippets for both domain-root and subdirectory installations.

---

## [1.0.1] - 2026-08-15

### Changed
* **Standalone Extension-Only Rewrite Entrypoint**:
  * Redesigned `rewrite.php` to reside exclusively inside `ext/phpbbseo/framework/rewrite.php`.
  * Completely eliminated the installation requirement to copy `rewrite.php` to the phpBB root directory.
  * Deterministic phpBB root filesystem discovery via `__DIR__` with safe failover.
  * Preserved full public `REQUEST_URI` and context mapping for canonical resolution without path leaks.
* **Official Branding & Domain**:
  * Updated official project website to [https://www.phpbbseo.com/](https://www.phpbbseo.com/).
* **Direct Footer Attribution**:
  * Implemented native phpBB template event `overall_footer_copyright_append.html` rendering `Powered by phpBB SEO` with `rel="nofollow"`.

---

## [1.0.0] - 2026-08-14

### Initial Public Release — phpBB SEO Lite 1.0.0

#### Added
* **Shared Core SEO URL Engine**:
  * Zero-SQL overhead runtime link rewriting for `append_sid()` hot paths.
  * Inbound routing resolution mapping clean URLs to native phpBB controllers.
  * Dedicated standalone `rewrite.php` bootstrap router.
* **Persistent Slug Index**:
  * Unified `phpbb_seo_slugs` database infrastructure for Forums, Topics, Members, and Groups.
  * Automatic slug collision resolution with UTF-8 Unicode transliteration.
  * CLI command (`phpbin/phpbbcli.php phpbbseo:rebuild-slugs`) for batch slug indexing.
* **Canonical & Redirection System**:
  * Automatic 301 Permanent Redirection from legacy native URLs (`viewtopic.php`, `viewforum.php`, `memberlist.php`) to canonical URLs.
  * Historical stale slug detection with 301 redirection to current canonical URLs.
  * URL safety validation preventing open-redirect vulnerabilities and query parameter corruption.
* **Configurable Permalinks**:
  * Customizable URL patterns with real-time compilation and route cache generation.
  * Preset selection (Default, Directory, Numeric, Custom).
* **Titles & Meta Engine (Lite)**:
  * Pattern-driven title template rendering for Home, Forum, Topic, and Profile pages.
  * Dynamic plain-text description normalization extracting clean summaries from posts.
  * Automated `<link rel="canonical">` and description meta tag injection via template events.
* **XML Sitemap Suite (Lite)**:
  * Dynamic Sitemaps Protocol 0.9 index and sub-sitemaps for Pages, Forums, and Topics.
  * 100% Zero-Offset keyset streaming architecture for infinite topic scalability.
  * Isolated Anonymous ACL enforcement preventing private forum exposure.
  * Human-friendly XSLT stylesheet (`/sitemap.xsl`) for in-browser visual inspection.
* **Modern Administration Control Panel (ACP)**:
  * Modern, responsive interface with fluid container widths and system typography.
  * Interactive previews, token helpers, and live sitemap statistics.
  * Form token CSRF validation and transactional rollback safety.
