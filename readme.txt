=== 4WP FAQ ===
Contributors: 4wpdev, anatolikkk
Tags: faq, accordion, json-ld, gutenberg, seo
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Smart FAQ wrapper for core Accordion: JSON-LD, optional registry, and per-block SEO—without duplicating your content.

== Description ==

4WP FAQ is a smart wrapper around WordPress core **Accordion** blocks. It adds FAQPage JSON-LD, an optional aggregated FAQ registry, and usage context while keeping your front-end design intact.

A plugin by [4wp.dev](https://4wp.dev/). **4WP** is our project brand; this plugin is not affiliated with, endorsed, or sponsored by WordPress.

Source code and releases: [github.com/4wpdev/4wp-faq](https://github.com/4wpdev/4wp-faq)

= Blocks =

* **4WP FAQ** (`forwp/faq`) — wrap Accordion / Accordion Item for FAQPage JSON-LD and registry scan
* **4WP FAQ List** (`forwp/faq-list`) — registry glossary: grouped or flat, include/exclude categories
* **4WP FAQ Categories** (`forwp/faq-categories`) — category nav that filters the List (Interactivity API)
* **4WP FAQ Count** (`forwp/faq-count`) — live count for All / current category / search (`[forwp_faq_count]`)
* **4WP FAQ Card** (`forwp/faq-card`) — inner card for the List (not shown in the inserter)

= Development =

JavaScript and CSS are built with `@wordpress/scripts`. Human-readable source (`src/`, `webpack.config.js`, `package.json`) is in the public GitHub repository above — not in the distributed plugin ZIP.

From a clone of the repository:

1. `cd` into the plugin directory
2. `npm install`
3. `npm run build` — compiles block, admin, and setup apps into `build/`

= Key features =

* Wrap **Accordion** / **Accordion Item** (legacy **Details** supported)
* **FAQPage JSON-LD** — site-wide toggle plus per-block override; hub pages output the questions actually shown
* Optional **FAQ registry** CPT with content scan and usage stats (“Used in” sources)
* **FAQ categories** — create in admin, pick per block, or auto-create on scan from the page
* **FSE hub blocks** — List, Categories, Count, and Card for registry-powered FAQ archives
* Preview count per category on the All view + optional pretty category URLs
* Drag-and-drop category order on the taxonomy screen
* **Polylang** — registry categories respect the language of the page where the FAQ block lives
* Setup wizard for registry post type and taxonomy slugs
* **Convert to FAQ** toolbar action on Accordion blocks
* Default **Accordion** template when inserting the FAQ block; **Add FAQ item** in the editor

= How it works =

1. Build FAQs with core Accordion blocks (or convert existing Accordion).
2. Click **Convert to FAQ** to wrap content in `forwp/faq`.
3. Enable JSON-LD in **FAQ → Settings** (recommended for SEO) or per block.
4. Optionally complete setup to aggregate questions into a registry list, then place List + Categories on a hub page.

JSON-LD on the front end does not require the registry. The registry is for browsing, reuse tracking, and hub layouts.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/4wp-faq/` or install from the Plugins screen.
2. Activate **4WP FAQ**.
3. Add or convert Accordion blocks, then open **FAQ → Settings** for JSON-LD and registry options.
4. After registry setup + rescan, add **4WP FAQ List** and **4WP FAQ Categories** on a page or template.

== Frequently Asked Questions ==

= Does this replace the Accordion block? =

No. 4WP FAQ is a thin wrapper. Your theme and block styles stay the same.

= Is JSON-LD enabled by default? =

No. Turn it on under **FAQ → Settings** (site-wide) or enable it for individual blocks in the block sidebar.

= Why does wordpress.org show only one block? =

Older packages registered List / Categories / Count from PHP only. From 2.2.0 each public block declares `editorScript` (and styles where applicable) in its `block.json` so the directory can discover them.

= What happens if I reset setup? =

You can change registry slugs in the wizard again, but FAQ **categories are removed**. Existing registry posts remain on the previous post type until you complete setup and run a new scan.

== Screenshots ==

1. 4WP FAQ block wrapping core Accordion in the editor
2. Settings — overview, rescan, JSON-LD, preview count, and pretty category URLs
3. FAQ hub — List + Categories (and Count) in the Site Editor

== Changelog ==

= 2.2.0 =
* Listing: declare `editorScript` / `style` in block.json for List, Categories, Count, and Card so wordpress.org discovers hub blocks (not only the FAQ wrapper).
* Contributors: add `anatolikkk` (Anatoliy Dovgun) alongside `4wpdev`.
* Add **4WP FAQ Count** block and `[forwp_faq_count]` shortcode.
* Hub: per-category preview on All view, “View all” links, SEO/pretty category URLs, category SEO title/description, drag-and-drop term order.
* Card: optional “Used in” source links from registry usage meta.
* Admin: preview-per-category and SEO URL settings; clearer JSON-LD help for hub pages.
* Stabilize readme Stable tag / plugin header / package.json on **2.2.0**.

= 2.1.0 =
* Add FSE registry hub blocks: **4WP FAQ List**, **4WP FAQ Card**, **4WP FAQ Categories**.
* Shared include/exclude filters between List and Categories (Interactivity API).
* Registry content helpers and front-end FAQ view script.

= 2.0.3 =
* Fix: FAQPage JSON-LD uses `<summary>` text as `Question.name` for core Details blocks (no longer duplicates the answer).
* Fix: FAQPage JSON-LD outputs in `<head>` for reliable detection by validators and crawlers.
* Add: `@id` and `url` on FAQPage schema.

= 2.0.2 =
* Maintenance release.

= 2.0.1 =
* Fix: incomplete plugin uploads no longer white-screen the site — missing PHP files show an admin error and the plugin stops loading safely.
* Deploy: ensure `includes/class-category-resolver.php` and other required files are present when updating from 2.0.0.

= 2.0.0 =
* Registry categories: none, existing, or create new per FAQ block (applied on scan).
* Categories can be managed in admin or derived from the pages where FAQ blocks are placed.
* Polylang: language-aware FAQ categories during registry scan.
* Block editor: default Accordion with one item on insert; **Add FAQ item** button.
* WPML support planned for a future release.

= 1.0.0 =
* Initial release: `forwp/faq` wrapper, setup wizard, registry scan, JSON-LD, admin settings UI.

== Upgrade Notice ==

= 2.2.0 =
Hub blocks visible on the plugin directory, Count block, preview/SEO URL settings, and Anatoliy Dovgun as contributor. Re-upload screenshots to SVN assets if captions still show empty images.

= 2.1.0 =
Adds registry FAQ List, Card, and Categories blocks for FSE hubs.

= 2.0.3 =
Fixes FAQPage schema for Details blocks and moves JSON-LD to the document head.

= 2.0.1 =
Hotfix for incomplete updates: safe load when plugin files are missing. Re-upload the full 2.0.1 package if you saw a fatal error on 2.0.0.

= 2.0.0 =
Registry categories, Polylang-aware scan, and improved FAQ block insertion (default Accordion + Add FAQ item).

= 1.0.0 =
Initial release.
