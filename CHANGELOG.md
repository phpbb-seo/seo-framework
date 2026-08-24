# Changelog

All notable changes to the **phpBB SEO Framework** project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
