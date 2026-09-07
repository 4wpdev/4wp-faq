# 4WP FAQ

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg?style=flat-square)](https://wordpress.org/)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg?style=flat-square)](https://www.php.net/)

**Not another FAQ block.** A smart wrapper around core **Accordion** that adds FAQPage JSON-LD, an optional question registry, and usage stats—without changing your front-end design or duplicating content.

A plugin by **[4WP](https://4wp.dev/)** · Source: **[github.com/4wpdev/4wp-faq](https://github.com/4wpdev/4wp-faq)** · Directory: [`readme.txt`](readme.txt)

## Quick start

1. Insert or select a core **Accordion**, then click **Convert to FAQ** in the toolbar (or insert **4WP FAQ**). Select existing **Details**, Accordion items, or heading + paragraph blocks and use **Transform to → 4WP FAQ**.
2. Write one question per Accordion Item (heading = question, panel = answer).
3. Open **FAQ → Settings** and turn on **FAQPage JSON-LD** when you want structured data for Google.
4. Optional: finish the registry setup wizard, run **Rescan**, then build a hub with **4WP FAQ List** + **4WP FAQ Categories**.

JSON-LD works **without** the registry. The registry is for browsing, reuse tracking, and hub pages.

## Features

- **`forwp/faq` wrapper** — keeps Accordion layout and theme styles
- **FAQPage JSON-LD** — site-wide toggle in Settings; per-block override; output in `<head>`
- **Convert to FAQ** — toolbar on Accordion / Accordion Item / Details / List; **Transform to → 4WP FAQ** for one or many selected items
- **Optional registry** — CPT + taxonomy (setup wizard), content scan, “Used in” sources
- **Admin Dashboard** — classic wp-admin widgets: status, categories (tree), reused, uncategorized; Rescan from Status
- **Drag-and-drop category order** — on **FAQ → FAQ Categories**; same order in admin lists, Dashboard, and front-end List / Categories
- **FSE hub blocks** — List, Categories, Count, Card
- **Pretty category URLs** — `/current-page/term-slug/` when Settings + Categories block allow it
- **Category SEO** — display title, SEO title (as-is when filled), SEO description
- **Polylang** — categories follow the language of the page that owns the FAQ block

Legacy **`core/details`** inside the wrapper is still supported for schema and scan.

## Admin menu (after registry setup)

| Screen | Who | Purpose |
|--------|-----|---------|
| **Dashboard** | Editors+ | Status metrics, Rescan, category tree, reused / uncategorized |
| **Add FAQ** | Editors+ | How-to (registry posts are created by scan, not by hand) |
| **All FAQs** | Editors+ | Registry list table |
| **Categories** | Editors+ | Hierarchy + **drag-and-drop order** (saved to `term_order`; used admin + front) |
| **Settings** | Admins | Overview stats, Rescan, JSON-LD, pretty URLs, title rules, Documentation |

## Block structure

**In-place FAQ (schema + scan):**

```
forwp/faq                    ← 4WP FAQ wrapper
└── core/accordion
    └── core/accordion-item  ← one Q&A (JSON-LD + registry)
        ├── heading
        └── panel content
```

**Registry hub (after setup + rescan):**

```
forwp/faq-categories         ← nav; filters the list (Interactivity API)
forwp/faq-list               ← grouped or flat registry list
└── forwp/faq-card           ← inner template
core/search                  ← optional; inspector “Filter 4WP FAQ List”
```

Details: [docs/BLOCKS.md](docs/BLOCKS.md).

## Display blocks (registry)

Typical layout: **Categories** in a sidebar, **List** in the main column.

| Block | Name | Role |
|---|---|---|
| **4WP FAQ List** | `forwp/faq-list` | Registry questions, grouped or flat; include/exclude terms. All view shows N per group + “View all”. |
| **4WP FAQ Card** | `forwp/faq-card` | Inner template (`inserter: false`). Accordion or heading + body; optional “Used in”. |
| **4WP FAQ Categories** | `forwp/faq-categories` | Parent → child nav. Pretty `/page/term-slug/` or in-place filter. |
| **4WP FAQ Count** | `forwp/faq-count` | Live count for All / category / search (`[forwp_faq_count]`). |

### Pretty category URLs (SEO)

1. **FAQ → Settings** → enable **Pretty category URLs**.
2. On the hub page, open **4WP FAQ Categories** → enable SEO-friendly category URLs there too.
3. Result: `/your-hub-page/term-slug/` with category Display title as H1; optional SEO title / description on the term; JSON-LD limited to that category.
4. Off: clicks filter the list **in place**; the page URL stays unchanged.

Filled category **SEO title** is used as the document title **as-is** (no “ – site name” suffix). Empty SEO title → display title + site name.

### Related Search

Core **Search** on the same page as the List → sidebar **4WP FAQ → Filter 4WP FAQ List**. Filters cards in place (not a site search).

## Install

| Source | Notes |
|--------|--------|
| **WordPress.org** | Install from Plugins → Add New, or see [`readme.txt`](readme.txt). |
| **GitHub** | Clone, build, activate (below). |

```bash
git clone https://github.com/4wpdev/4wp-faq.git
cd 4wp-faq
npm install && npm run build
# Copy or symlink into wp-content/plugins/4wp-faq and activate.
```

## Requirements

- WordPress **6.0+** (Accordion blocks; tested up to **7.1**)
- PHP **7.4+**

## Links

| | |
|---|---|
| Repository | [github.com/4wpdev/4wp-faq](https://github.com/4wpdev/4wp-faq) |
| Releases | [GitHub Releases](https://github.com/4wpdev/4wp-faq/releases) |
| Block reference | [docs/BLOCKS.md](docs/BLOCKS.md) |
| Roadmap | [docs/ROADMAP.md](docs/ROADMAP.md) |
| WordPress.org readme | [readme.txt](readme.txt) |

## For developers

- **Namespace:** `ForWP\FAQ`
- **Blocks:** `forwp/faq`, `forwp/faq-list`, `forwp/faq-card`, `forwp/faq-categories`, `forwp/faq-count` · **Text domain:** `4wp-faq`
- **REST:** `forwp-faq/v1` (settings, registry scan, setup)
- **Build:** `npm run build` → `build/`

```bash
npm install
npm run build   # production
npm run start   # watch
```

Release ZIPs should include `build/` and PHP only—see [`.distignore`](.distignore).

## License

GPL v2 or later. See [readme.txt](readme.txt) and [`4wp-faq.php`](4wp-faq.php).
