# Changelog

All notable changes to the **phpBB SEO Framework** project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.5] - 2026-09-09

### Performance
- **Post-Position Fast Path**: Pre-calculated post positions (`prev_posts`) during `viewtopic.php` execution within `SeoListener::onViewTopicPosts` when chronological display (`post_time ASC`) and zero unapproved/soft-deleted posts are present. Eliminates redundant database lookups for on-page jump/quote permalinks, reducing page generation overhead to extension-disabled baseline levels (~0.113s).

### Fixed
- **RecentTopics Malformed Outbound URLs**: Added listeners for `avathar.recenttopics.modify_tpl_ary` and `paybas.recenttopics.modify_tpl_ary` (`onRecentTopicsModifyTplAry`) to heal naive third-party parameter concatenation (`&amp;view=unread#unread`) appended directly onto slash-terminated SEO URLs.
- **Inbound Query Parameter Fail-Safe**: Added route-level normalization in `rewrite.php` and `InboundRouteResolver` to gracefully recover and redirect malformed URLs containing trailing `/&` or `/&amp;` query separators.
- **Illegal Superglobal Access**: Fixed fatal error (`deactivated_super_global`) triggered in `SeoListener` on requests with non-print `view` query parameters (e.g., `view=unread`) by migrating legacy `$_GET['view']` checks to phpBB's `$request` service abstraction.

### Improved
- **Clean Topic Permalinks**: Excluded redundant `'f'` (forum ID) parameter from rewritten topic permalinks in `PublicResourceUrlResolver`. In phpBB, topic rows inherently store their parent `forum_id`; stripping this redundant query argument ensures clean, canonical topic URLs and prevented masking third-party concatenation bugs during testing.
- **Route Cache File Path Robustness**: Normalized `$storeDir` in `RouteCacheCompiler` to guarantee a trailing slash, preventing malformed cache file paths when initialized with directory paths lacking trailing slashes.

---

## [1.1.4] - 2026-09-08

### Fixed
- **Critical Performance Fix for `prev_posts` Range Query (FORCE INDEX & Safe Fallback)**:
  - Fixed a critical performance issue where v1.1.3's range query (`topic_id = ? AND post_visibility = 1 AND post_time < ?`) could still be misjudged by MySQL's cost-based optimizer as `index_merge (intersect: topic_id, post_visibility)` on large production databases (~0.29s per call, accumulating ~1.23s of latency on affected pages), despite testing fast on test copies.
  - Added an explicit `FORCE INDEX (tid_post_time)` hint on MySQL/MariaDB connections for Query 1, locking the query execution plan to `type: range` and completely preventing MySQL from selecting inefficient `index_merge` scans.
  - Hardened with automatic safe fallback: if `tid_post_time` is missing or modified for any reason (custom schemas, third-party alterations, legacy tables), error 1176 is caught gracefully without throwing fatal errors (`E_USER_ERROR`), immediately falling back to the plain query and caching this state in memory for the remainder of the request.
  - Non-MySQL database drivers (PostgreSQL, SQLite, Oracle, MSSQL) cleanly bypass the hint and execute standard SQL.
  - **Live Production Verification**: Confirmed fixed on an active production board by the reporting user — query execution time dropped from ~290-310ms to ~5-9ms, reducing total page generation time from ~1.33s to ~0.167s with EXPLAIN confirming `type: range, key: tid_post_time`.
  - **Upgrade Recommendation**: All users running v1.1.2 or v1.1.3 are strongly urged to upgrade to v1.1.4 immediately.

---

## [1.1.3] - 2026-09-07

### Fixed
- **Critical Performance Fix for `prev_posts` COUNT Query**:
  - Resolved a critical performance regression in v1.1.2 where the historical `prev_posts` COUNT query in `SlugRepository::countPrecedingPosts` could trigger an unpredictable and slow MySQL `index_merge (intersect: topic_id, post_visibility)` execution plan on production-scale databases (~0.28s per query, adding ~0.86s+ per page).
  - Rewrote the compound `OR` predicate `(post_time < ? OR (post_time = ? AND post_id <= ?))` by splitting it into two mutually disjoint, unambiguous indexed queries:
    1. Strictly earlier posts: `WHERE topic_id = ? AND post_visibility = 1 AND post_time < ?` (unambiguous range scan using `tid_post_time`).
    2. Shared timestamp tiebreaker: `WHERE topic_id = ? AND post_visibility = 1 AND post_time = ? AND post_id <= ?` (instant point lookup using `tid_post_time`).
  - Benchmarked across production-scale datasets (15,000+ post topics) and edge cases (shared timestamps, unapproved posts) confirming consistent sub-millisecond to low-single-digit millisecond execution times (0.1ms - 10ms maximum) in all cases with 100% mathematical accuracy.
  - **Upgrade Recommendation**: All users running v1.1.2 are strongly recommended to upgrade to v1.1.3 immediately, especially if unexpected page load slowdowns were experienced after updating to v1.1.2.

---

## [1.1.2] - 2026-09-07

### Fixed
- **Link-Rewriting Performance & N+1 Query Elimination**:
  - Resolved performance regression on board index and topic listings (`viewforum.php`) by implementing zero-query fast paths in `SlugRepository` for topic first post (`prev_posts = 0`) and last post (`topic_posts_approved - 1`).
  - Added batch post position pre-seeding across `onViewForumTopics` and `onDisplayForums`, eliminating repetitive post position queries during template rendering.
- **XML Sitemap Cache Invalidation**:
  - Injected `SitemapRepository` into `SeoListener` to automatically purge sitemap statistics and boundary caches upon new topic creation (`onSubmitPostEnd`) and topic deletion (`onDeleteTopicsAfter`).
  - Subscribed to `core.approve_topics_after` and `core.approve_posts_after` to persist topic slugs and invalidate sitemap caches when queued topics are approved via MCP.
  - Enhanced `purgeStatsCache()` to invalidate boundary maps across standard chunk sizes (500 to 50,000) and custom configured values.

---

## [1.1.1] - 2026-09-06

### Fixed
- **Canonical Pagination & Entity Encoding**:
  - Resolved an issue where entity-encoded ampersands (single `&amp;start=` or double `&amp;amp;start=`) and zero-initialized global request states shadowed the query parameter, causing native paginated URLs (e.g. `viewtopic.php?t=X&start=Y`) to lose their offset and redirect to page 1.
  - Implemented recursive entity-decoding for request query strings prior to parameter extraction.
- **Direct Post Resolution & Deep Linking**:
  - Resolved direct post requests (`viewtopic.php?p=POST_ID`) to the specific paginated canonical URL (e.g. `/topic/slug-ID/page/X/#pPOST_ID`) based on the post's actual position (`prev_posts`) within the topic and board visibility rules, ensuring the `#p...` DOM anchor is directly reachable.
  - Prevented generation of invalid phantom canonical URLs (such as `-0/`) when an unresolvable post ID is requested, correctly failing closed to 404.
- **Routing Parameter Sanitization**:
  - Stripped single- and deeply-encoded routing parameter leftovers (e.g. `?amp%3Bamp%3Bstart=20`) from redirect target URLs, avoiding stray or malformed query strings.
- **Precedence Conflict Resolution**:
  - Prioritized the verified post position over any conflicting or stale explicit `start` parameter whenever a resolvable post ID is present, ensuring visitors land on the page containing the post.
  - Hardened query exclusion regex with `preg_quote` consistency across `RedirectResolver` and `PublicResourceUrlResolver`.

### Improved
- Clean preservation of non-routing tracking query parameters (`utm_*`, `gclid`, etc.) preceding fragment anchors in canonical redirect targets.

---

## [1.1.0] - 2026-09-05

### Added
- **Persistent Slug Backfill Engine**:
  - Unified `SlugBackfillManager` service executing zero-offset keyset pagination (`topic_id > last_id`), multi-row DBAL insertion (`sql_multi_insert`), and missing-only filtering.
  - Concurrency mutex locking powered by phpBB core's `\phpbb\lock\db('seo_slug_rebuild_lock')` preventing race conditions between CLI and ACP.
  - **ACP AJAX Stepped Runner**: Interactive "Rebuild Missing Slugs" tool in the ACP Sitemap tab with real-time percentage progress bar, pause/resume capability, and automated sitemap cache invalidation upon completion.
  - **Refactored CLI Command**: `php bin/phpbbcli.php seo:rebuild-slugs` and `phpbbseo:rebuild-slugs` updated to consume `SlugBackfillManager` with `--all` and `--batch-size` options, eliminating N+1 writes and LIMIT/OFFSET pagination.
  - Dedicated ACP AJAX route `/seo/rebuild-slugs/batch` with session verification, administrator authorization (`a_`, `a_board`), and CSRF token protection.

---

## [1.0.9] - 2026-09-05


### Added
- **Legacy Ultimate SEO URL (USU) Migration & Compatibility**:
  - Full backward-compatibility resolver (`UsuMigrationResolver`) for legacy Ultimate SEO URL patterns:
    - Topics (`{slug}-t{id}.html`, `t{id}.html`, `.htm`, extensionless, underscore delimiters, nested subfolders, pagination `-s{start}`).
    - Forums (`{slug}-f{id}.html`, `f{id}.html`, `.htm`, extensionless, underscore delimiters, pagination `-s{start}`).
    - Members (`member{id}.html`, `user{id}.html`, `user_{id}.html`, extensionless, `.htm`).
    - Posts (`post{id}.html`, `post_{id}.html`, extensionless, `.htm`).
  - Seamless 301 Permanent Redirect pipeline mapping all legacy USU formats to canonical modern SEO URLs.
  - **Approach A Route Collision Avoidance**: Integrates directly with phpBB's Symfony Router to prevent legacy USU patterns from shadowing routes registered by the core or other extensions.
  - **Fail-Closed Safety**: In the event of unexpected exceptions during route matching, safely aborts legacy claim to protect native routing.
  - Configurable toggle in ACP Permalinks (`phpbbseo_legacy_usu_enabled`), defaulted to OFF (`false`) with a clear warning notice advising admins to only enable if migrating from USU.
  - Pre-bootstrap route compiler integration in `RouteCacheCompiler` matching the active configuration.

- **ACP Safe Uninstall Module**:
  - Dedicated Safe Uninstall manager (`SafeUninstallManager`) and ACP module.
  - Provides pre-flight diagnostics, `.htaccess` rewrite rules cleanup preview, route cache purging, and step-by-step guidance for clean, zero-downtime deactivation or removal.
  - Comprehensive unit test coverage for safe uninstall workflows.

### Improved
- Modernized ACP administrative styling and navigation headers.
- Route cache synchronization and lifecycle persistence.

---

## [1.0.8] - 2026-08-26

### Fixed
- Fixed compatibility with third-party extensions using phpBB path_helper for filesystem paths.
- Fixed DMZX Watermark image processing when SEO URLs are enabled.
- Removed global PHPBB_USE_BOARD_URL_PATH override that could convert expected filesystem-relative paths into board URLs.
- Improved nested SEO URL static asset resolution.

### Improved
- Preserve non-routing tracking/query parameters during canonical redirects, including UTM parameters, gclid, fbclid, and highlight.

---

## [1.0.7] - 2026-08-25

### Fixed
* **Topic & Post URL Anchor Resolution**:
  * Improved topic/post URL anchor handling and prevented malformed or duplicated post fragments (`#pXX`) across canonical and rewrite resolvers.
* **Slug Entity & Unicode Normalization**:
  * Improved nested HTML entity decoding during SEO slug generation.
  * Improved Unicode NFC normalization for cleaner and more consistent SEO slugs.
* **Server Environment & Sub-Request Emulation**:
  * Improved rewrite compatibility with phpBB/Symfony sub-requests and controllers in `rewrite.php`.

### Improved
* **SEO Metadata Head Integration Architecture**:
  * Improved SEO metadata output architecture for cleaner and extensible `<head>` integration.
* **Sitemap Filtering**:
  * Improved sitemap filtering for publicly accessible forum content.
* **Admin Interface**:
  * Improved ACP navigation tabs and visual compatibility.

---

## [1.0.6] - 2026-08-21

### Fixed
* **Real-Render SEO Titles & Meta Single Escaping Pipeline**:
  * Normalized all token-rendered strings in `MetadataPatternRenderer::render()` by decoding presentation HTML entities (`html_entity_decode`) prior to output.
  * Wrapped all resolver endpoints in `MetadataResolver` with `PlainTextNormalizer::normalize(..., 0)`.
  * Guaranteed raw plain semantic text for browser DOM (`document.title` and `textContent`) while ensuring strictly single-escaped output (`&amp;`, `&quot;`, `&#039;`, `&lt;`, `&gt;`) in raw HTML source and total absence of `&amp;amp;`.
* **Clean Semantic Slug Generation & HTML Entity Decoding**:
  * Updated `DefaultSlugGenerator::generate()` to decode HTML entities before stripping non-alphanumerics, eliminating premature entity encoding artifacts (e.g. `Installation & Configuration` converting to `-amp-` instead of `-`).
  * Added full multilingual Unicode preservation with word-boundary normalization.
* **Authoritative Slug Lifecycle & Historical 301 Redirect Normalization**:
  * Verified full slug lifecycle: Title change $\rightarrow$ New Slug $\rightarrow$ Historical Slug Recognition $\rightarrow$ Single-hop Direct 301 $\rightarrow$ Zero Redirect Chains ($A \rightarrow C$ and $B \rightarrow C$ both direct 301s).
  * Preserved `#pXXX` post anchors and query parameters seamlessly across historical 301 canonical redirects.

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

### Added
* **Multilingual Slug Options & Fallback Generator**:
  * Added transliteration and multilingual slug normalization options supporting non-Latin scripts (Persian, Arabic, Cyrillic, Greek, CJK) with deterministic fallback generation.
* **Safe Inbound Query Parameter Preservation**:
  * Added classification and passthrough for non-SEO tracking/filtering query parameters (`utm_*`, `gclid`, `fbclid`, `highlight`, `view`, `style`, `ch`).

### Fixed
* **Canonical URL Scheme & Host Determination**:
  * Improved CanonicalResolver reliability to strictly respect configured board URL settings (`server_name`, `server_port`, `script_path`, `cookie_secure`) avoiding reverse proxy port leaks.
* **Legacy 301 Redirect Loop Prevention**:
  * Ensured native phpBB URLs (`viewtopic.php`, `viewforum.php`, `memberlist.php`) redirect to canonical permalinks only when query parameters match canonical identity, preventing infinite redirect chains.

---

## [1.0.1] - 2026-08-14

### Added
* **Titles & Meta Resolution Engine**:
  * Automated generation of SEO titles and meta descriptions for Board Index, Forums, Topics, and Member Profiles with configurable patterns.
* **Dynamic XML Sitemap Generator**:
  * Sitemaps for Index, Topics, Forums, and Users with Google-compliant formatting, automatic pagination, and custom XSL stylesheets.
* **Comprehensive ACP Control Panel**:
  * Full administrative interface under the dedicated **SEO** tab with Dashboard, Permalinks, Titles & Meta, XML Sitemap, and Health Check modules.

---

## [1.0.0] - 2026-08-13

### Added
* Initial release of **phpBB SEO Framework Lite**.
* Core High-Performance Permalink & Rewrite Engine with zero runtime SQL queries on cached paths.
* Zero-Core-Modification architecture fully utilizing phpBB 3.3 extension events and pre-bootstrap rewrite proxy.
* Strict 301 canonical redirects for legacy and stale topic/forum URLs.
* Multi-webserver configuration generators for Apache (`.htaccess`), Nginx, and LiteSpeed.
