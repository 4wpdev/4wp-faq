# 4WP FAQ

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress 6.6+](https://img.shields.io/badge/WordPress-6.6%2B-blue.svg?style=flat-square)](https://wordpress.org/)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg?style=flat-square)](https://www.php.net/)

**Not another FAQ block.** A smart wrapper around core **Accordion** that adds FAQPage JSON-LD, an optional question registry, and usage stats—without changing your front-end design or duplicating content. Optional **AI Assistant** fills FAQ category copy through the standard **WordPress AI Client / Connectors**.

A plugin by **[4WP](https://4wp.dev/)** · Source: **[github.com/4wpdev/4wp-faq](https://github.com/4wpdev/4wp-faq)** · Directory: [`readme.txt`](readme.txt)

## Quick start

1. Insert or select a core **Accordion**, then click **Convert to FAQ** in the toolbar (or insert **4WP FAQ**). Select existing **Details**, Accordion items, or heading + paragraph blocks and use **Transform to → 4WP FAQ**.
2. Write one question per Accordion Item (heading = question, panel = answer).
3. Open **FAQ → Settings** and turn on **FAQPage JSON-LD** when you want structured data for Google.
4. Optional: finish the registry setup wizard, run **Rescan**, then build a hub with **4WP FAQ List** + **4WP FAQ Categories**.
5. Optional: configure **Settings → Connectors**, enable **FAQ → Settings → AI**, then **Generate with AI** on a category.

JSON-LD works **without** the registry. The registry is a helper: scan unique questions, edit one record to push Q/A onto every **Used In** page, or **Add FAQ** onto category pages / one page. Accordion items on the site stay the source of truth.

## Version 2.6.3

- **Typography:** List, Card, and Categories panels match core (size, appearance, line height, letter spacing, decoration, letter case).
- **Used in:** sources stay on one line with a separator. Non-public sources are hidden on the front; the editor preview shows them greyed.
- **Count:** the badge is the sum of the categories on All, or that category’s count on a category page. The number and the label stay on one line.
- **No results:** an empty search shows a message you can edit, the same way as Query Loop no-results.
- **Dashboard:** figures follow the Polylang admin language and name it. All languages adds a per-language breakdown. Reused counts public pages only.
- **Editor preview:** List and Categories follow the page language, and Exclude hides that category.

## Version 2.6.2

- **Editor:** List, Categories, and Count previews use POST, so “Error loading block: [object Object]” does not replace the preview.
- **Answers:** output drops color classes copied from dark sections, so the answer uses the card color.
- **Settings:** Overview blocks (Registry, SEO, AI, Category titles) open that section. Shorter card headers; the Connectors notice is separated from the toggle.
- **Dashboard:** FAQ Status shows large figures. Two-column layout stacks the third column on the right. A short FAQ widget on the main WordPress Dashboard links here.

## Version 2.6.1

- **4WP Drive** — FAQ wrap for document import: Heading 2 FAQ section + Heading 3 Q&A map to `forwp/faq`. Activate this plugin so Drive Patterns can list the wrap. [4WP Drive on WordPress.org](https://wordpress.org/plugins/4wp-drive/).

## Version 2.6.0

- **Edit** a registry FAQ (title + answer) → **Update** writes that item onto every **Used In** page. A renamed question is found via the previous title.
- **Add FAQ:** categories → every matching `forwp/faq` block; optional **one page**; neither → dropped on scan.
- New answers reuse the sibling accordion-item wrapper; wrapped HTML is saved back on the registry record.
- Rescan / reinstall rebuilds the CPT from page content.

## Version 2.5.0

- **AI Assistant** on FAQ category edit: Description, Display title, SEO title, SEO description via **Settings → Connectors** (`wp_ai_client_prompt` / `wp_supports_ai`)
- Settings opt-in: **Allow predefined field markup**. Apply writes into the form; Update still saves.

## AI Assistant (WordPress Connectors)

Generation uses the **WordPress AI Client**. Configure a text-generation connector under **Settings → Connectors**. Until a connector is available, the Settings **AI** card and the Generate panel stay off.

1. Finish registry setup so FAQ categories exist.
2. Open **Settings → Connectors** and complete the connector for text generation.
3. Under **FAQ → Settings → AI**, turn on **Allow predefined field markup**.
4. Edit a term on **FAQ → Categories** and **Generate with AI**. The prompt uses the category name and questions in that term (registry titles).
5. Review the four fields, Apply, then **Update**. Nothing is saved until you update the term.

The plugin does not ship API keys or talk to a model until the site administrator has configured a connector.

## Features

- **`forwp/faq` wrapper** — keeps Accordion layout and theme styles
- **FAQPage JSON-LD** — site-wide toggle in Settings; per-block override; output in `<head>`
- **Convert to FAQ** — toolbar on Accordion / Accordion Item / Details / List; **Transform to → 4WP FAQ** for one or many selected items
- **Optional registry** — CPT + taxonomy, scan, **Used In**, edit-to-push, **Add FAQ** onto category pages or one page
- **Admin Dashboard** — classic wp-admin widgets: status, categories (tree), reused, uncategorized; Rescan from Status
- **Drag-and-drop category order** — on **FAQ → FAQ Categories**; same order in admin lists, Dashboard, and front-end List / Categories
- **FSE hub blocks** — List, Categories, Count, Card
- **Collapsible category nav** — parents start closed; visitors expand them and the browser remembers
- **Pretty category URLs** — `/current-page/term-slug/` when Settings + Categories block allow it
- **Category SEO** — display title, SEO title (as-is when filled), SEO description
- **Polylang** — categories follow the language of the page that owns the FAQ block
- **AI Assistant** — WordPress AI Client connectors; generate category description and SEO fields (opt-in)

Legacy **`core/details`** inside the wrapper is still supported for schema and scan.

## Admin menu (after registry setup)

| Screen | Who | Purpose |
|--------|-----|---------|
| **Dashboard** | Editors+ | Status metrics, Rescan, category tree, reused / uncategorized |
| **Add FAQ** | Editors+ | New question + answer; place via FAQ categories and/or one page |
| **All FAQs** | Editors+ | Registry list table |
| **Categories** | Editors+ | Hierarchy + **drag-and-drop order** (saved to `term_order`; used admin + front) |
| **Settings** | Admins | Overview stats, Rescan, JSON-LD, pretty URLs, optional **AI** (Connectors), title rules, Documentation |

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
| **4WP FAQ Categories** | `forwp/faq-categories` | Parent → child nav (subcategories collapsed by default). Pretty `/page/term-slug/` or in-place filter. |
| **4WP FAQ Count** | `forwp/faq-count` | Live count for All / category / search (`[forwp_faq_count]`). |

### Pretty category URLs (SEO)

1. **FAQ → Settings** → enable **Pretty category URLs**.
2. On the hub page, open **4WP FAQ Categories** → enable SEO-friendly category URLs there too.
3. Result: `/your-hub-page/term-slug/`. **On-page title** comes from the category Display title (empty: category name) in these core blocks: **Title** (`core/post-title`), **Query Title** / Archive title (`core/query-title`), **Term Name** (`core/term-name`). Heading level is the template’s choice; Heading blocks are not swapped. **On-page description** comes from the category Description in **Excerpt** (`core/post-excerpt`) or **Term Description** (`core/term-description`). Document title / meta still use SEO title / SEO description. Canonical and `og:url` are the category URL. **SEO image** on the term is used for Open Graph (empty: hub page image). The List omits the group H2 when that title is already in the page heading. JSON-LD is limited to that category.
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

- WordPress **6.6+** (Accordion blocks; tested up to **7.1**)
- PHP **7.4+**
- Optional: **WordPress AI Client** + a text-generation connector under **Settings → Connectors**

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
- **REST:** `forwp-faq/v1` (settings, registry scan, setup, category AI prompt/generate)
- **Build:** `npm run build` → `build/`

```bash
npm install
npm run build   # production
npm run start   # watch
```

Release ZIPs should include `build/` and PHP only—see [`.distignore`](.distignore).

## License

GPL v2 or later. See [readme.txt](readme.txt) and [`4wp-faq.php`](4wp-faq.php).
