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
- **FSE display blocks** — glossary-style list, cards, and category nav from the registry
- **Admin settings** — overview metrics, rescan, SEO toggle, reset setup (with safeguards)

Legacy **`core/details`** inside the wrapper is still supported for schema and scan.

## Block structure

**In-place FAQ (schema + scan):**

```
forwp/faq                    ← 4WP FAQ wrapper
└── core/accordion
    └── core/accordion-item  ← one Q&A (JSON-LD + registry)
        ├── heading
        └── panel content
```

**Registry hub (FSE, after setup + rescan):**

```
forwp/faq-categories         ← nav; filters the list (Interactivity API)
forwp/faq-list               ← grouped or flat registry list
└── forwp/faq-card           ← inner template (accordion or heading + answer)
core/search                  ← optional; inspector “Filter 4WP FAQ List”
```

Details: [docs/BLOCKS.md](docs/BLOCKS.md).

## Display blocks (registry)

These blocks read the FAQ registry CPT, not the in-place Accordion. Typical layout: **4WP FAQ Categories** in a sidebar column, **4WP FAQ List** in the main column.

| Block | Name | Role |
|---|---|---|
| **4WP FAQ List** | `forwp/faq-list` | Renders registry questions, grouped by category or as a single list. Include/exclude taxonomy terms. |
| **4WP FAQ Card** | `forwp/faq-card` | Inner block of the List (`inserter: false`). Accordion or heading + description. Optional source links (“Used in”), optional post-type label. |
| **4WP FAQ Categories** | `forwp/faq-categories` | Vertical or horizontal nav. Click a category to filter the List in place (Interactivity). |

### Shared category filters

List and Categories **share include/exclude**. They stay in sync even when they sit in different columns.

- If the List includes two categories, the nav shows those two.
- If the nav includes more, the List includes those too.
- Empty include on one side inherits the other. Empty on both = all categories.
- **All categories** in the nav (toggle + replaceable label, default “All categories”) shows the **full synced list**, not every FAQ on the site.

On the front end, PHP also unions non-empty includes from both blocks in the same page/template so an older save (List = 2, nav = all) cannot list a category that was never queried.

### Card options

Set on the inner **4WP FAQ Card** (copied to the List for editor preview):

- **Display** — accordion (`<details>`) or heading + body
- **Show sources** — pages/posts that use the question
- **Sources label** — placeholder, default `Used in` (empty hides the label)
- **Show post type** — off by default; CPT name next to each source link

### Categories nav

- **Orientation** — vertical or horizontal
- **Label** — heading above the links (default `Categories`)
- **Show “All categories”** — on by default; label is editable
- **Show counts** — term counts next to each link
- **SEO-friendly category URLs** — off: filter in place, URL unchanged. On: one extra path segment after the current page (`/faq/term-slug/`)

### Related Search

On a core **Search** block: **4WP FAQ → Filter 4WP FAQ List**. The input filters visible cards via the Interactivity store (`forwp/faq`) and does not submit a search request.

## How it works

1. Build or select a core **Accordion** (or item) in the editor.
2. Click **Convert to FAQ** in the block toolbar.
3. Under **FAQ → Settings**, turn on JSON-LD when you want structured data (off by default).
4. Optionally run **setup** to enable the registry CPT (`faq` by default) and **Rescan** after content changes.

JSON-LD on the front end does **not** require the registry. The registry is for listing, reuse tracking, and the display blocks above.

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
- **Blocks:** `forwp/faq`, `forwp/faq-list`, `forwp/faq-card`, `forwp/faq-categories` · **Text domain:** `4wp-faq`
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
