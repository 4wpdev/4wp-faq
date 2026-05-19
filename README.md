# 4WP FAQ

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg?style=flat-square)](https://wordpress.org/)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg?style=flat-square)](https://www.php.net/)

**Not another FAQ block.** A smart wrapper around core **Accordion** that adds FAQPage JSON-LD, an optional question registry, and usage stats—without changing your front-end design or duplicating content.

A plugin by **[4WP](https://4wp.dev/)** · Source: **[github.com/4wpdev/4wp-faq](https://github.com/4wpdev/4wp-faq)**

## Features

- **`forwp/faq` wrapper** — keeps your Accordion layout and theme styles
- **FAQPage JSON-LD** — site-wide toggle in Settings; per-block override in the sidebar
- **Convert to FAQ** — toolbar action on `core/accordion` / `core/accordion-item`
- **Optional registry** — aggregated CPT + taxonomy (setup wizard), content scan, reuse stats
- **Admin settings** — overview metrics, rescan, SEO toggle, reset setup (with safeguards)

Legacy **`core/details`** inside the wrapper is still supported for schema and scan.

## Block structure

```
forwp/faq                    ← 4WP FAQ wrapper
└── core/accordion
    └── core/accordion-item  ← one Q&A (JSON-LD + registry)
        ├── heading
        └── panel content
```

Details: [docs/BLOCKS.md](docs/BLOCKS.md).

## How it works

1. Build or select a core **Accordion** (or item) in the editor.
2. Click **Convert to FAQ** in the block toolbar.
3. Under **FAQ → Settings**, turn on JSON-LD when you want structured data (off by default).
4. Optionally run **setup** to enable the registry CPT (`faq` by default) and **Rescan** after content changes.

JSON-LD on the front end does **not** require the registry. The registry is for listing, reuse tracking, and future features.

## Install

| Source | Notes |
|--------|--------|
| **From GitHub** | Clone, build, activate (see below). |
| **WordPress.org** | Coming with v1.0.0 review — listing copy in [`readme.txt`](readme.txt). |

```bash
git clone https://github.com/4wpdev/4wp-faq.git
cd 4wp-faq
npm install && npm run build
# Copy or symlink into wp-content/plugins/4wp-faq and activate in wp-admin.
```

## Requirements

- WordPress **6.0+** (Accordion blocks; tested up to **6.9**)
- PHP **7.4+**

## Links

| | |
|---|---|
| Repository | [github.com/4wpdev/4wp-faq](https://github.com/4wpdev/4wp-faq) |
| Releases | [GitHub Releases](https://github.com/4wpdev/4wp-faq/releases) |
| Block reference | [docs/BLOCKS.md](docs/BLOCKS.md) |
| WordPress.org readme | [readme.txt](readme.txt) (Plugin Check / directory listing) |

## For developers

- **Namespace:** `ForWP\FAQ`
- **Block:** `forwp/faq` · **Text domain:** `4wp-faq`
- **REST:** `forwp-faq/v1` (settings, registry scan, setup)
- **Build:** `npm run build` → `build/` (block editor + admin React screens)

```bash
npm install
npm run build   # production
npm run start   # watch
```

Release ZIPs should include `build/` and PHP only—see [`.distignore`](.distignore) (excludes `src/`, `node_modules/`, etc.).

## License

GPL v2 or later. See [readme.txt](readme.txt) and the plugin header in [`4wp-faq.php`](4wp-faq.php).
