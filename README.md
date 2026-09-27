# phpBB SEO Framework

Modern, enterprise-grade Search Engine Optimization infrastructure for phpBB.

[![phpBB Version](https://img.shields.io/badge/phpBB-3.3.0--3.3.15+-blue.svg)](https://www.phpbb.com/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%20%7C%208.2%20%7C%208.3%20%7C%208.4-777bb4.svg)](https://php.net/)
[![License: GPL-2.0](https://img.shields.io/badge/License-GPL--2.0--only-green.svg)](LICENSE)
[![GitHub Release](https://img.shields.io/github/v/release/phpbb-seo/seo-framework.svg)](https://github.com/phpbb-seo/seo-framework/releases/tag/v1.2.5)

---

## Overview

**phpBB SEO Framework (Lite Edition)** is the official search engine optimization extension for phpBB 3.3.x communities. Architected for speed, clean software design, and strict standards compliance, it delivers a zero-SQL hot-path URL rewrite engine, automated metadata generation, keyset-streamed XML sitemaps with constant memory footprint, built-in multi-platform 301 migration, high-performance static asset delivery, and a high-performance bulk slug backfill engine.

* **Official Website**: [https://www.phpbbseo.com/](https://www.phpbbseo.com/)
* **GitHub Repository**: [https://github.com/phpbb-seo/seo-framework](https://github.com/phpbb-seo/seo-framework)
* **Latest Release**: [v1.2.5](https://github.com/phpbb-seo/seo-framework/releases/tag/v1.2.5)

---

## Core Features (Lite Edition)

### 🔗 Zero-SQL SEO URL Engine
* **Zero Database Queries on Hot Path**: Runtime outbound link rewriting through `append_sid()` operates entirely in-memory with **0 SQL queries**.
* **Configurable Permalinks**: Flexible URL pattern templates for Forums, Topics, Member Profiles, and Usergroups (Modern, Compact, Classic, and fully customizable structures).
* **Persistent Slug Read-Model**: Authoritative slug index ensuring stable, deterministic URL resolution without altering phpBB core database tables.
* **Automatic 301 Canonical Redirects**: Seamlessly redirects legacy native URLs (`viewtopic.php?t=123`, `viewforum.php?f=4`), altered slugs, and outdated URL structures to current canonical locations.
* **Pre-Bootstrap Router**: Dedicated standalone routing handler (`rewrite.php`) intercepting inbound requests before full application initialization for blazing fast responses.
* **Administrative Path Isolation**: Strict isolation guards guaranteeing that URLs and requests targeting ACP (`adm/`, `IN_ADMIN`), MCP (`mcp.php`), or UCP (`ucp.php`) are never rewritten, preserving native administrative pagination and actions.
* **Query Parameter Healing**: Automatic normalization and healing of malformed query attachments (e.g. `/&view=print` or `/&bookmark=1`), ensuring seamless compatibility across phpBB templates.

### 🔄 Multi-Platform 301 Migration & Compatibility
* **Third-Party Platform Migration Redirects**: Built-in 301 redirection for communities migrating to phpBB from **XenForo**, **vBulletin**, **MyBB**, and **SMF**, featuring safe ID preservation and fail-closed route collision guards.
* **Legacy Ultimate SEO URL (USU) Support**: Full backward compatibility resolving and 301-redirecting historical `.html`, `.htm`, extensionless, underscore-based, and nested directory USU patterns.
* **Symfony Route Collision Guard**: Candidate legacy URLs are validated against phpBB's Symfony route collection to prevent shadowing core routes or third-party extensions.
* **Configurable Admin Toggles**: Independent ACP switches for each legacy platform to eliminate runtime overhead for unneeded patterns.

### ⚡ High-Performance Static Asset Delivery & 404 Fast-Path
* **Zero-Redirect Direct Asset Streaming**: Misplaced or rewritten theme/extension static assets are delivered directly (`200 OK`) with full HTTP caching (`Cache-Control: public, max-age=31536000, immutable`, `ETag`, `Last-Modified`, `304 Not Modified`, and GZIP compression).
* **Lightweight 404 Fast-Path**: Missing static assets (`.png`, `.jpg`, `.js`, `.css`, `.woff2`, etc.) receive an instant ~15ms HTTP 404 before full phpBB session boot, eliminating session locking contention and server stalls.
* **Extensible Pro Fast-Path Hooks**: Pre-boot router hooks enabling Pro features (such as `/llms.txt` cache serving) to deliver payloads with zero database queries.

### 🗺️ Keyset-Streamed XML Sitemap Suite
* **Sitemaps Protocol 0.9 Compliant**: Fully compatible with Google Search Console, Bing Webmaster Tools, and Yandex.
* **Scalable Keyset Streaming**: Employs Symfony `StreamedResponse` and database cursor batching with a constant `< 2 MB` memory footprint, effortlessly handling massive forums with 100k+ topics without PHP memory exhaustion.
* **Strict Anonymous ACL Guard**: Strictly restricts sitemaps to public guest-accessible forums; private, hidden, or staff forums are never exposed.
* **Human-Readable XSL Stylesheet**: Interactive browser presentation (`/sitemap.xsl`) transforming raw XML feeds into styled dashboards.

### 📝 Dynamic Titles & Meta Engine
* **Pattern-Based Templates**: Define custom meta titles and descriptions for Home, Forum, Topic, and Profile pages.
* **Intelligent Text Normalization**: Automatically strips BBCode, smilies, nested quotes, and HTML entities to produce clean plain-text Unicode snippets.
* **Authoritative Canonical Links**: Injects `<link rel="canonical">` elements across all endpoints, eliminating duplicate content across pagination and print views.

### 🌐 Universal Multilingual & Unicode Support
* **Native UTF-8 Slugs**: Full native Unicode slug generation across Persian, Arabic, Cyrillic, Latin, Greek, Hebrew, and CJK scripts.
* **Configurable Latin Diacritics Transliteration**: Optional ACP toggle utilizing Unicode Form D decomposition to convert accented Latin characters (`é`, `ö`, `ñ`) into clean ASCII while strictly preserving non-Latin scripts.

### 🛡️ ACP Safe Uninstall Suite
* **Pre-Flight Diagnostics**: Inspects server rewrite capabilities, active slug counts, and system states before deactivation.
* **Automated `.htaccess` Fallback Preview**: Generates clean fallback rewrite rules and allows reviewing removal steps with automatic backup recovery.
* **Clean Deactivation**: Automatically unlinks compiled route caches and temporary stores upon deactivation.

---

## Lite vs Pro Edition

phpBB SEO Framework is designed with a unified, extensible architecture. The Lite Edition delivers the complete foundational SEO infrastructure, while the Pro Edition unlocks advanced search engine automation, real-time analytics, and rich data integrations.

| Feature / Capability | Lite Edition | Pro Edition |
| :--- | :---: | :---: |
| **Zero-SQL SEO URL Hot Path** | ✅ Included | ✅ Included |
| **Persistent Slug Read-Model** | ✅ Included | ✅ Included |
| **Canonical 301 Redirections** | ✅ Included | ✅ Included |
| **Configurable Permalinks (Modern, Compact, Classic, Custom)** | ✅ Included | ✅ Included |
| **Multi-Platform 301 Migration (XenForo, vB, MyBB, SMF, USU)** | ✅ Included | ✅ Included |
| **Administrative Path Isolation (ACP, MCP, UCP)** | ✅ Included | ✅ Included |
| **High-Performance Static Asset Delivery & 404 Fast-Path** | ✅ Included | ✅ Included |
| **Keyset-Streamed XML Sitemaps (Symfony StreamedResponse)** | ✅ Included | ✅ Included |
| **Dynamic Titles & Meta Engine** | ✅ Included | ✅ Included |
| **Unicode UTF-8 & Diacritics Transliteration** | ✅ Included | ✅ Included |
| **Safe Uninstall Suite & Pre-Flight Diagnostics** | ✅ Included | ✅ Included |
| **Persistent Slug Backfill Engine (CLI & ACP)** | ✅ Included | ✅ Included |
| **AI Agent `/llms.txt` Generator & Endpoint** | ❌ | ✅ Included |
| **On-Page SEO Analyzer (Real-time scoring & audits)** | ❌ | ✅ Included |
| **Titles & Meta Pro (Advanced Rules & Per-Forum Overrides)** | ❌ | ✅ Included |
| **OpenGraph & Twitter Card Social Metadata** | ❌ | ✅ Included |
| **JSON-LD Schema & Rich Data Generator** | ❌ | ✅ Included |
| **Google Search Console (GSC) Integration** | ❌ | ✅ Included |
| **Automated 404 Error Monitor & Dead Link Tracking** | ❌ | ✅ Included |
| **Dynamic 301/302 Redirect Manager** | ❌ | ✅ Included |
| **Robots.txt & Advanced Indexing Directives** | ❌ | ✅ Included |
| **IndexNow Instant Search Engine Submission** | ❌ | ✅ Included |
| **Forum Social & OpenGraph Fallbacks** | ❌ | ✅ Included |

Learn more about the Pro Edition at [https://www.phpbbseo.com/](https://www.phpbbseo.com/).

---

## Requirements

* **phpBB**: `3.3.0` – `3.3.15+` (fully compatible across all phpBB 3.3.x releases)
* **PHP**: `8.1`, `8.2`, `8.3`, or `8.4+` (verified with Extension Pre Validator standards)
* **Web Servers**:
  * Apache 2.4+ (with `mod_rewrite` enabled)
  * LiteSpeed / OpenLiteSpeed
  * Nginx

---

## Installation & Upgrade

### Fresh Installation

1. Download the latest release package (`phpbbseo_framework_1.2.5.zip`) from the [Releases](https://github.com/phpbb-seo/seo-framework/releases) page.
2. Extract the archive and upload the files to your phpBB installation so the directory path is:
   ```text
   ext/phpbbseo/framework/
   ```
3. Configure your web server rewrite rules:

   #### Apache / LiteSpeed
   Add the following directives to your phpBB root `.htaccess` file:
   ```apache
   <IfModule mod_rewrite.c>
       RewriteEngine On
       RewriteCond %{REQUEST_FILENAME} !-f
       RewriteCond %{REQUEST_FILENAME} !-d
       RewriteRule ^(.*)$ ext/phpbbseo/framework/rewrite.php [QSA,L]
   </IfModule>
   ```

   #### Nginx (Domain Root)
   Add the following directive inside your server configuration block:
   ```nginx
   location / {
       try_files $uri $uri/ /ext/phpbbseo/framework/rewrite.php?$query_string;
   }
   ```

   #### Nginx (Subdirectory, e.g. `/forum/`)
   ```nginx
   location /forum/ {
       try_files $uri $uri/ /forum/ext/phpbbseo/framework/rewrite.php?$query_string;
   }
   ```

4. Open the **Administration Control Panel (ACP)** and navigate to:  
   **Customise** &raquo; **Manage Extensions**
5. Locate **phpBB SEO Framework** under *Disabled Extensions* and click **Enable**.
6. Navigate to the new **SEO Framework** tab in the ACP to configure your settings.

### Upgrading an Existing Installation

1. In the ACP, navigate to **Customise** &raquo; **Manage Extensions**, find **phpBB SEO Framework**, and click **Disable** (do *not* click delete data).
2. Upload the new files to `ext/phpbbseo/framework/`, overwriting existing files.
3. Click **Enable** on **phpBB SEO Framework**.
4. Purge the board cache from **ACP** &raquo; **General** &raquo; **Purge the cache**.

---

## CLI Command Reference

The framework includes a powerful Symfony CLI command suite for server administrators:

```bash
# Rebuild missing topic slugs (default safe mode, processes unindexed topics)
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
* **Community Forum**: [https://www.phpbbseo.com/forum/](https://www.phpbbseo.com/forum/)
* **GitHub Repository**: [https://github.com/phpbb-seo/seo-framework](https://github.com/phpbb-seo/seo-framework)
* **Issue Tracker**: [https://github.com/phpbb-seo/seo-framework/issues](https://github.com/phpbb-seo/seo-framework/issues)

---

## License

phpBB SEO Framework is open-source software licensed under the **GNU General Public License v2 (GPL-2.0-only)**.

---

## Credits

Developed and maintained by the **[phpBB SEO](https://www.phpbbseo.com/)** Team.  
Copyright &copy; 2026 phpBB SEO. All rights reserved.