=== 4WP FAQ ===
Contributors: 4wpdev, anatolikkk
Tags: faq, accordion, json-ld, schema, seo, gutenberg, faqpage
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

FAQPage schema for core Accordion, optional FAQ registry hub, category SEO, and drag-and-drop category order—without duplicating content or fighting your theme.

== Description ==

4WP FAQ wraps WordPress core **Accordion** blocks so you can keep your design and still get:

* **FAQPage JSON-LD** for Google rich results (off by default; turn on when you are ready)
* An optional **FAQ registry** that aggregates questions across the site after a scan
* **Hub pages** with List + Categories blocks, pretty category URLs, category SEO titles, and drag-and-drop category order (admin + front end)
* **Drag-and-drop FAQ category order** on **FAQ → Categories** — same order in admin lists and on the front end (List / Categories hub)

A plugin by [4wp.dev](https://4wp.dev/). **4WP** is our project brand; this plugin is not affiliated with, endorsed, or sponsored by WordPress.

Source: [github.com/4wpdev/4wp-faq](https://github.com/4wpdev/4wp-faq)

= How to work with it (short path) =

1. In the editor, add a core **Accordion** (or select an existing one).
2. Click **Convert to FAQ** in the block toolbar (or insert the **4WP FAQ** block). You can also select existing **Details**, **Accordion**, or heading + paragraph blocks and use **Transform to → 4WP FAQ**.
3. Put the **question** in the Accordion Item heading and the **answer** in the panel.
4. Open **FAQ → Settings** and enable **FAQPage JSON-LD** if you want structured data on the front end.
5. Optional: complete registry setup → **Rescan** (Dashboard or Settings) → place **4WP FAQ List** and **4WP FAQ Categories** on a hub page or template.

Registry posts are created by the scan. Do not create them by hand under “Add New”.

= Blocks =

* **4WP FAQ** (`forwp/faq`) — wrap Accordion / Accordion Item for JSON-LD and registry scan
* **4WP FAQ List** (`forwp/faq-list`) — registry glossary: grouped or flat, include/exclude categories
* **4WP FAQ Categories** (`forwp/faq-categories`) — category nav that filters the List
* **4WP FAQ Count** (`forwp/faq-count`) — live count for All / category / search (`[forwp_faq_count]`)
* **4WP FAQ Card** (`forwp/faq-card`) — inner card for the List (not in the inserter)

= Key features =

* Wrap **Accordion** / **Accordion Item** (legacy **Details** still supported)
* **FAQPage JSON-LD** in the document head — site-wide toggle plus per-block override
* Optional **FAQ registry** CPT with scan and “Used in” source links
* **FAQ categories** — manage in admin, assign per block, or create on scan from the page
* **Admin Dashboard** — status metrics, Rescan, hierarchical categories, reused / uncategorized
* **FSE hub blocks** — List, Categories, Count, Card
* Preview count per category on the All view + optional pretty category URLs (`/hub-page/term-slug/`)
* Category SEO title / description fields; filled SEO title is used as-is (no site-name suffix)
* Drag-and-drop category order on **FAQ → FAQ Categories** — same order in admin lists, Dashboard, and front-end List / Categories blocks
* **Polylang** — categories follow the language of the page that owns the FAQ block
* Setup wizard for registry post type and taxonomy slugs
* **Convert to FAQ** toolbar and **Transform to → 4WP FAQ** (Accordion, Details, List, heading + paragraph — one or many); default Accordion template on insert

= Development =

JavaScript and CSS are built with `@wordpress/scripts`. Human-readable source (`src/`, `webpack.config.js`, `package.json`) is in the public GitHub repository — not in the distributed ZIP.

From a clone:

1. `cd` into the plugin directory
2. `npm install`
3. `npm run build` — compiles block, admin, and setup apps into `build/`

== Installation ==

1. Upload to `/wp-content/plugins/4wp-faq/` or install from **Plugins → Add New**.
2. Activate **4WP FAQ**.
3. Add or convert Accordion blocks, then open **FAQ → Settings** for JSON-LD and registry options.
4. After registry setup + Rescan, add **4WP FAQ List** and **4WP FAQ Categories** on a page or template.

== Frequently Asked Questions ==

= Does this replace the Accordion block? =

No. 4WP FAQ is a thin wrapper. Your theme and block styles stay the same. Accordion stays Accordion—4WP FAQ adds schema, optional registry, and hub tools around it.

= How do I add a new FAQ question? =

1. Edit a page or post.
2. Insert **Accordion** (or **4WP FAQ**, which starts with an Accordion).
3. Click **Convert to FAQ** if you started from a plain Accordion.
4. Or select one or more **Details** / **Accordion Item** blocks (or heading + paragraph pairs) and use **Transform to → 4WP FAQ**.
5. One Accordion Item = one question (heading) and one answer (panel).
6. If you use the registry: open **FAQ → Dashboard** or **Settings** and run **Rescan registry** so the question appears under All FAQs.

There is no manual “Add New” registry editor. **FAQ → Add FAQ** is a short how-to only.

= Is JSON-LD / FAQ schema enabled by default? =

No. Structured data is opt-in so you control when Google sees FAQ markup.

* Site-wide: **FAQ → Settings → FAQPage JSON-LD**
* Per block: block sidebar → JSON-LD on front end (On / Off / inherit)

Schema is printed in the document **head** on singular pages that contain FAQ blocks. The registry is **not** required for JSON-LD.

= How do I check that FAQ schema is working? =

1. Enable JSON-LD in Settings (or for the specific block).
2. View the published page.
3. Use [Google Rich Results Test](https://search.google.com/test/rich-results) or view page source and search for `FAQPage`.

If Yoast SEO is active and outputs FAQ schema for the same content, 4WP FAQ skips its own output to avoid duplicate FAQPage graphs.

= What is the FAQ registry? =

An optional post type that **aggregates** unique questions found in your content after a scan. Use it to:

* Browse all questions in one place
* See where a question is reused (“Used in”)
* Build a public FAQ hub with List + Categories blocks

In-place FAQ blocks on product or landing pages keep working either way.

= How do I build an FAQ hub page? =

1. Complete registry setup and run **Rescan**.
2. Create a page (for example `/faq/`).
3. Add **4WP FAQ Categories** (nav) and **4WP FAQ List** (questions).
4. Optionally add core **Search** and enable **Filter 4WP FAQ List** in that block’s sidebar.
5. Optionally add **4WP FAQ Count**.

List and Categories share include/exclude filters so the nav and list stay aligned.

= What are pretty category URLs? =

When **Pretty category URLs** is on in Settings **and** on the Categories block:

* Category links become `/your-hub-page/term-slug/`
* The category **Display title** is used as H1
* Optional category **SEO title** / **description** drive the document title and meta
* JSON-LD on that URL is limited to that category

When off, category clicks filter the list **in place** and the page URL does not change.

= How do category SEO titles work? =

On each FAQ category term you can set **SEO title** and **SEO description**.

* If **SEO title** is filled → the browser / document title uses that string **exactly** (no “ – site name” appended).
* If it is empty → Display title + site name (WordPress default pattern).

See **FAQ → Settings → Documentation** for H1 / title / meta rules.

= When should I Rescan? =

After you add, edit, or remove FAQ blocks on published content. Rescan is available on:

* **FAQ → Dashboard** (Status widget)
* **FAQ → Settings** (Rescan registry)

Scans also schedule automatically when relevant posts are saved.

= Why does wordpress.org show only one block? =

Older packages registered List / Categories / Count from PHP only. From 2.2.0 each public block declares `editorScript` (and styles where applicable) in its `block.json` so the directory can discover them. Update to the latest ZIP if the listing looks incomplete.

= How do I filter the FAQ list with Search? =

Add a core **Search** block on the same page as **4WP FAQ List**. In the block sidebar: **4WP FAQ → Filter 4WP FAQ List**. Typing filters cards in place and does not run a site search.

= Does the plugin duplicate my FAQ text? =

No for the front end of in-place blocks—the Accordion content you write is the source of truth. The registry stores aggregated copies for listing and stats; answers still come from scanned content, not a second public editor workflow.

= What happens if I reset setup? =

You can choose new registry slugs in the wizard again, but FAQ **categories are removed**. Existing registry posts remain on the previous post type until you complete setup and run a new scan.

= Does it work with Polylang? =

Yes for registry categories during scan: terms follow the language of the page that contains the FAQ block. WPML is not integrated yet.

= Can editors use the Dashboard? =

Yes. **FAQ → Dashboard** is available to users who can edit posts. **Rescan** and **Settings** require manage options (administrators).

== Screenshots ==

1. 4WP FAQ block wrapping core Accordion in the editor
2. Settings — rescan, JSON-LD, preview count, pretty category URLs, and how category titles are built
3. FAQ hub — List + Categories (and Count) in the Site Editor

== Changelog ==

= 2.3.0 =
* Convert selected **Details**, **Accordion** / **Accordion Item**, heading+paragraph pairs, or a **List** to **4WP FAQ** via Transform to and the Convert to FAQ toolbar (one or many items).
* Category SEO title: when filled, the document title is used as-is (no site-name suffix).
* Admin **Dashboard** under the FAQ menu: status metrics, Rescan, hierarchical By category (collapsible, inclusive counts), reused / uncategorized.
* **FAQ Categories**: drag-and-drop term order applies in admin lists and on the front end (ensures `term_order` column when missing).
* Settings: compact layout plus how H1 / document title / meta description are built for category URLs.
* Docs: hub blocks for page building and core Search → Filter 4WP FAQ List.
* Add FAQ in admin is a short how-to (registry posts are created by scan, not by hand).
* List/Card: Question / Answer typography on the Styles tab.

= 2.2.0 =
* Listing: declare `editorScript` / `style` in block.json for List, Categories, Count, and Card so wordpress.org discovers hub blocks (not only the FAQ wrapper).
* Contributors: add `anatolikkk` (Anatoliy Dovgun) alongside `4wpdev`.
* Add **4WP FAQ Count** block and `[forwp_faq_count]` shortcode.
* Hub: per-category preview on All view, “View all” links, SEO/pretty category URLs, category SEO title/description, drag-and-drop term order.
* Card: optional “Used in” source links from registry usage meta.
* Admin: preview-per-category and SEO URL settings; clearer JSON-LD help for hub pages.

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

= 2.3.0 =
Dashboard, drag-and-drop category order, category title rules, Transform to 4WP FAQ for selected Details/Accordion items, and filled SEO titles no longer append the site name.

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
