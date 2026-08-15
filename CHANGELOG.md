# Changelog

All notable changes to the **phpBB SEO Framework** project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
