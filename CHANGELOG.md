# Changelog

All notable changes to the **phpBB SEO Framework** project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.1] - 2026-08-15

### Changed
* **Official Branding & Domain**:
  * Updated official project website to [https://www.phpbbseo.com/](https://www.phpbbseo.com/).
* **Footer Attribution Setting**:
  * Added ACP configuration toggle `seo_footer_attribution_enable` (default: Enabled) under General Settings on the SEO Dashboard.
  * Implemented native phpBB template event `overall_footer_copyright_append.html` rendering `Powered by phpBB SEO` with `rel="nofollow"`.
  * Verified 0 attribution HTML output when disabled in ACP.

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
