# phpBB SEO Framework

Modern, enterprise-grade Search Engine Optimization infrastructure for phpBB.

[![phpBB Version](https://img.shields.io/badge/phpBB-3.3.0--3.3.17+-blue.svg)](https://www.phpbb.com/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%20%7C%208.0%20%7C%208.1%20%7C%208.2%20%7C%208.3-777bb4.svg)](https://php.net/)
[![License: GPL-2.0](https://img.shields.io/badge/License-GPL--2.0--only-green.svg)](LICENSE)
[![GitHub Release](https://img.shields.io/github/v/release/phpbb-seo/seo-framework.svg)](https://github.com/phpbb-seo/seo-framework/releases/tag/v1.1.0)

---

## Overview

**phpBB SEO Framework (Lite Edition)** is the official search engine optimization extension for phpBB 3.3.x communities. Architected for speed, clean software design, and strict standards compliance, it delivers a zero-SQL hot-path URL rewrite engine, automated metadata generation, dynamic keyset-streamed XML sitemaps, built-in migration from legacy SEO systems, and a high-performance bulk slug backfill engine.

* **Official Website**: [https://www.phpbbseo.com/](https://www.phpbbseo.com/)
* **GitHub Repository**: [https://github.com/phpbb-seo/seo-framework](https://github.com/phpbb-seo/seo-framework)
* **Latest Release**: [v1.1.0](https://github.com/phpbb-seo/seo-framework/releases/tag/v1.1.0)

---

## What's New in Version 1.1.0

### 🚀 Persistent Slug Backfill Engine
* **High-Throughput Keyset Bulk Indexing**: Built specifically for large boards with tens of thousands to 500,000+ topics. Uses zero-offset cursor pagination (`topic_id > :last_id ORDER BY topic_id ASC LIMIT :batch_size`) to eliminate SQL `OFFSET` performance degradation.
* **Strictly Bounded Memory**: Streams batches with predictable, flat memory consumption (< 3 MB peak overhead), preventing PHP memory exhaustion on any hosting environment.
* **Missing-Only Indexing Mode**: Automatically detects and processes only topics lacking an indexed slug via database anti-joins.
* **Multi-Row Atomic Insertions**: Batched inserts executed within transactional boundaries for maximum integrity and throughput.
* **Concurrency Mutex Locking**: Utilizes phpBB core's database lock provider (`\phpbb\lock\db`) to prevent race conditions between simultaneous background or web executions.
* **Deferred Sitemap Cache Invalidation**: Intelligently invalidates XML sitemap caches only once upon 100% completion of the backfill run.

### ⚡ Interactive ACP AJAX Runner
* **Smart Notice Card**: Automatically detected and displayed in the ACP XML Sitemap section whenever missing topic slugs exist.
* **Stepped Progress Bar**: Displays real-time percentage, processed topic counts, and status indicators without reloading the page.
* **Pause & Resume**: Gracefully handles network drops or server timeouts with one-click resumption from the exact last processed ID.
* **In-Place Completion**: Real-time counter synchronization immediately updates missing slug statistics to `0`.

### 🖥️ Enhanced CLI Rebuild Suite
* Full Symfony Console support via `php bin/phpbbcli.php seo:rebuild-slugs` (and alias `phpbbseo:rebuild-slugs`).
* New `--all` flag to force rebuild all topic slugs from scratch.
* Configurable `--batch-size` parameter (1 to 1,000 topics per batch).
* Real-time terminal progress indicators displaying batch IDs, remaining counts, and elapsed execution timing.

---

## Core Features (Lite Edition)

### 🔗 Zero-SQL SEO URL Engine
* **Zero Database Queries on Hot Path**: Runtime outbound link rewriting through `append_sid()` operates in-memory with **0 SQL queries**.
* **Configurable Permalinks**: Flexible URL pattern templates for Forums, Topics, Member Profiles, and Usergroups with customizable tokens.
* **Persistent Slug Read-Model**: Authoritative slug index ensuring stable, deterministic URL resolution without altering phpBB core database tables.
* **Automatic 301 Canonical Redirects**: Automatically redirects legacy native URLs (`viewtopic.php?t=123`, `viewforum.php?f=4`), altered slugs, and outdated URLs to current canonical structures.
* **Pre-Bootstrap Router**: Dedicated standalone routing handler (`rewrite.php`) intercepting inbound requests before full application initialization for blazing fast responses.

### 🔄 Legacy Ultimate SEO URL (USU) Migration & Compatibility
* **Full Backward Compatibility**: Seamlessly resolves and 301-redirects legacy Ultimate SEO URL structures across topics, forums, members, and posts.
* **Universal Format Support**: Handles `.html`, `.htm`, extensionless, underscore-based, and nested directory legacy patterns.
* **Approach A Router Collision Guard**: Pre-checks candidate legacy URLs against phpBB's Symfony route collection to prevent shadowing core routes or third-party extensions.
* **Configurable Admin Toggle**: Easily enabled or disabled from the ACP Permalinks settings.

### 🛡️ ACP Safe Uninstall Suite
* **Pre-Flight Diagnostics**: Inspects server rewrite capabilities, active slug counts, and system states before deactivation.
* **Automated `.htaccess` Fallback Preview**: Generates clean fallback rewrite rules and allows reviewing removal steps with automatic backup recovery.
* **Clean Deactivation**: Automatically unlinks compiled route caches and temporary stores upon deactivation.

### 📝 Dynamic Titles & Meta Engine
* **Pattern-Based Templates**: Define custom meta titles and descriptions for Home, Forum, Topic, and Profile pages.
* **Intelligent Text Normalization**: Automatically strips BBCode, smilies, nested quotes, and HTML entities to produce clean plain-text Unicode snippets.
* **Canonical Link Tags**: Injects authoritative `<link rel="canonical">` elements to eliminate duplicate content issues.

### 🗺️ XML Sitemap Suite
* **Sitemaps Protocol 0.9 Compliant**: Fully compatible with Google Search Console, Bing Webmaster Tools, and Yandex.
* **Scalable Keyset Streaming**: Capable of indexing massive forum catalogues with zero offset slowdowns.
* **Strict Anonymous ACL Guard**: Strictly restricts sitemaps to public guest-accessible forums; private, hidden, or staff forums are never exposed.
* **Human-Readable XSL Stylesheet**: Interactive browser presentation (`/sitemap.xsl`) transforming raw XML feeds into styled dashboards.

### 🌐 Universal Multilingual & Unicode Support
* Full native UTF-8 slug generation across Persian, Arabic, Cyrillic, Latin, and CJK alphabets.
* Graceful fallback transliteration for non-Latin character sets.

---

## Lite vs Pro Edition

phpBB SEO Framework is designed with a unified, extensible architecture. The Lite Edition delivers the complete foundational SEO infrastructure, while the Pro Edition unlocks advanced search engine automation, real-time analytics, and rich data integrations.

| Feature / Capability | Lite Edition | Pro Edition |
| :--- | :---: | :---: |
| **Zero-SQL SEO URL Hot Path** | ✅ Included | ✅ Included |
| **Persistent Slug Read-Model** | ✅ Included | ✅ Included |
| **Canonical 301 Redirections** | ✅ Included | ✅ Included |
| **Configurable Permalinks** | ✅ Included | ✅ Included |
| **Legacy USU 301 Migration Resolver** | ✅ Included | ✅ Included |
| **Safe Uninstall Suite** | ✅ Included | ✅ Included |
| **Persistent Slug Backfill Engine (CLI & ACP)** | ✅ Included | ✅ Included |
| **XML Sitemap Engine & XSL Styling** | ✅ Included | ✅ Included |
| **Dynamic Titles & Meta Engine** | ✅ Included | ✅ Included |
| **On-Page SEO Analyzer** | ❌ | ✅ Included |
| **Titles & Meta Pro (Advanced Rules)** | ❌ | ✅ Included |
| **OpenGraph & Twitter Card Metadata** | ❌ | ✅ Included |
| **JSON-LD Schema & Rich Data Generator** | ❌ | ✅ Included |
| **Google Search Console (GSC) Integration** | ❌ | ✅ Included |
| **Automated 404 Error Monitor** | ❌ | ✅ Included |
| **Dynamic 301/302 Redirect Manager** | ❌ | ✅ Included |
| **Robots.txt & Advanced Indexing Rules** | ❌ | ✅ Included |
| **IndexNow Instant Search Submission** | ❌ | ✅ Included |
| **Forum Social & OpenGraph Fallbacks** | ❌ | ✅ Included |

Learn more about the Pro Edition at [https://www.phpbbseo.com/](https://www.phpbbseo.com/).

---

## Requirements

* **phpBB**: `3.3.0` – `3.3.17+`
* **PHP**: `7.4`, `8.0`, `8.1`, `8.2`, or `8.3` (tested and verified on PHP 8.2 & 8.3)
* **Supported Web Servers**:
  * Apache 2.4+ (with `mod_rewrite` enabled)
  * LiteSpeed / OpenLiteSpeed
  * Nginx

---

## Installation

1. Download the latest release package (`phpbbseo_framework_1.1.0.zip`) from the [Releases](https://github.com/phpbb-seo/seo-framework/releases) page.
2. Extract the archive and upload the files to your phpBB installation so the directory path is:
   ```text
   ext/phpbbseo/framework/
   ```
3. Configure your web server rewrite rules:

   ### Apache / LiteSpeed
   Add the following directives to your phpBB root `.htaccess` file:
   ```apache
   <IfModule mod_rewrite.c>
       RewriteEngine On
       RewriteCond %{REQUEST_FILENAME} !-f
       RewriteCond %{REQUEST_FILENAME} !-d
       RewriteRule ^(.*)$ ext/phpbbseo/framework/rewrite.php [QSA,L]
   </IfModule>
   ```

   ### Nginx (Domain Root)
   Add the following directive inside your server configuration block:
   ```nginx
   location / {
       try_files $uri $uri/ /ext/phpbbseo/framework/rewrite.php?$query_string;
   }
   ```

   ### Nginx (Subdirectory, e.g. `/forum/`)
   ```nginx
   location /forum/ {
       try_files $uri $uri/ /forum/ext/phpbbseo/framework/rewrite.php?$query_string;
   }
   ```

4. Open the **Administration Control Panel (ACP)** and navigate to:  
   **Customise** &raquo; **Manage Extensions**
5. Locate **phpBB SEO Framework** under *Disabled Extensions* and click **Enable**.
6. Navigate to the new **SEO Framework** tab in the ACP to configure your settings.

---

## CLI Command Reference

The framework includes a powerful Symfony CLI command suite for server administrators:

```bash
# Rebuild missing topic slugs (default safe mode)
php bin/phpbbcli.php seo:rebuild-slugs

# Rebuild all topic slugs from scratch
php bin/phpbbcli.php seo:rebuild-slugs --all

# Rebuild with a custom batch size (1 to 1000)
php bin/phpbbcli.php seo:rebuild-slugs --batch-size=500

# Using the alternative namespace alias
php bin/phpbbcli.php phpbbseo:rebuild-slugs --all
```

---

## Documentation & Community

* **Official Website & Docs**: [https://www.phpbbseo.com/](https://www.phpbbseo.com/)
* **Knowledge Base**: [https://www.phpbbseo.com/knowledge](https://www.phpbbseo.com/knowledge)
* **GitHub Repository**: [https://github.com/phpbb-seo/seo-framework](https://github.com/phpbb-seo/seo-framework)
* **Issue Tracker**: [https://github.com/phpbb-seo/seo-framework/issues](https://github.com/phpbb-seo/seo-framework/issues)

---

## License

phpBB SEO Framework is open-source software licensed under the **GNU General Public License v2 (GPL-2.0-only)**.

---

## Credits

Developed and maintained by the **[phpBB SEO](https://www.phpbbseo.com/)** Team.  
Copyright &copy; 2026 phpBB SEO. All rights reserved.