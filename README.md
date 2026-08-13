# phpBB SEO Framework

Modern SEO infrastructure for phpBB.

[![phpBB Version](https://img.shields.io/badge/phpBB-3.3.x-blue.svg)](https://www.phpbb.com/)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-777bb4.svg)](https://php.net/)
[![License: GPL-2.0](https://img.shields.io/badge/License-GPL--2.0--only-green.svg)](LICENSE)
[![Latest Release](https://img.shields.io/badge/Release-v1.0.0-blue.svg)](https://github.com/phpbb-seo/seo-framework/releases)

---

## Overview

**phpBB SEO Framework (Lite Edition v1.0.0)** is the official next-generation search engine optimization platform for phpBB 3.3.x boards. Built from the ground up for extreme performance, clean architecture, and standards compliance, it replaces legacy URL rewrite modifications with a modern, zero-SQL hot-path rewrite engine and dynamic SEO tools.

Official Website: [https://www.phpbbseo.ir/](https://www.phpbbseo.ir/)  
GitHub Repository: [https://github.com/phpbb-seo/seo-framework](https://github.com/phpbb-seo/seo-framework)

---

## Key Features

### 🚀 Shared Core SEO URL Engine
* **Zero SQL Overhead on Hot Path**: Runtime outbound link rewriting via `append_sid()` operates with **0 database queries**.
* **Configurable Permalinks**: Flexible URL pattern templates for Forums, Topics, Member Profiles, and Usergroups.
* **Persistent Slug Index**: High-speed slug cache preventing collisions while preserving historical URLs.
* **Automatic 301 Canonical Redirects**: Seamlessly redirects legacy native URLs (`viewtopic.php?t=123`) and renamed/stale slugs to current canonical URLs without search ranking loss.
* **Full Multilingual & Unicode Support**: Native UTF-8 slug generation across Persian, Arabic, Cyrillic, Latin, and CJK alphabets.

### 🏷️ Titles & Meta Engine (Lite)
* **Custom Meta Titles & Descriptions**: Configurable pattern-based title templates for Home, Forums, Topics, and Members.
* **Intelligent Text Normalization**: Strips BBCode, smilies, quotes, and HTML entities to produce clean Unicode meta descriptions.
* **Canonical Link Tags**: Injects authoritative `<link rel="canonical">` tags into every public resource.

### 🗺️ XML Sitemap Suite (Lite)
* **Standards Compliant**: Generates dynamic Sitemaps Protocol 0.9 feeds for search engines (Google, Bing, Yandex).
* **Zero-Offset Scalability**: High-efficiency keyset streaming architecture capable of handling millions of topics with bounded (< 5 MB) memory usage and **zero deep SQL offsets**.
* **Strict Anonymous ACL Isolation**: Guarantees private and staff forums are never exposed in search sitemaps regardless of who requests the sitemap.
* **Human-Readable XSL Styling**: Beautiful, responsive browser presentation (`/sitemap.xsl`) transforming raw XML feeds into modern dashboards.

---

## Requirements

* **phpBB**: 3.3.0 or higher
* **PHP**: 8.1, 8.2, 8.3, or higher
* **Web Server**: Apache (with `mod_rewrite`), Nginx, or LiteSpeed

---

## Installation

1. Download the latest release from the [Releases](https://github.com/phpbb-seo/seo-framework/releases) page.
2. Unpack the archive and copy the folder contents to your phpBB board at:
   ```
   ext/phpbbseo/framework/
   ```
3. Copy `ext/phpbbseo/framework/rewrite.php` to your phpBB root directory (adjacent to `app.php` and `index.php`).
4. Ensure standard `.htaccess` URL rewrite rules are active in your phpBB root.
5. In your phpBB Administration Control Panel (ACP), navigate to:
   **Customise** &raquo; **Manage Extensions**
6. Locate **phpBB SEO Framework** under *Disabled Extensions* and click **Enable**.
7. Navigate to the new **SEO Framework** tab in the ACP to configure your settings.

---

## Product Editions & Architecture

phpBB SEO is designed with a strict single-core architecture:

* **phpBB SEO Lite (This Release)**: Core URL Engine, Permalinks, Persistent Slugs, Canonical Redirects, Titles & Meta, and XML Sitemap.
* **phpBB SEO Pro (Planned Future Edition)**: Advanced OpenGraph / Social Cards, JSON-LD Schema & Rich Data, 404 & Redirect Manager, and On-Page SEO Analyzer.

Pro features will extend the same Shared Core APIs without duplicate URL generation or separate routing systems.

---

## License

phpBB SEO Framework is open-source software licensed under the **GNU General Public License v2 (GPL-2.0-only)**.

Copyright &copy; 2026 [phpBB SEO](https://www.phpbbseo.ir/). All rights reserved.
