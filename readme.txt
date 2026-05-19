=== 4WP FAQ ===
Contributors: 4wpdev
Tags: faq, accordion, json-ld, gutenberg, seo
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Smart FAQ wrapper for core Accordion: JSON-LD, optional registry, and per-block SEO—without duplicating your content.

== Description ==

4WP FAQ is a smart wrapper around WordPress core **Accordion** blocks. It adds FAQPage JSON-LD, an optional aggregated FAQ registry, and usage context while keeping your front-end design intact.

Source and releases: [github.com/4wpdev/4wp-faq](https://github.com/4wpdev/4wp-faq)

= Key features =

* Wrap **Accordion** / **Accordion Item** (legacy **Details** supported)
* **FAQPage JSON-LD** — site-wide toggle plus per-block override
* Optional **FAQ registry** CPT with content scan and usage stats
* Setup wizard for registry post type and taxonomy slugs
* **Convert to FAQ** toolbar action on Accordion blocks

= How it works =

1. Build FAQs with core Accordion blocks (or convert existing Accordion).
2. Click **Convert to FAQ** to wrap content in `forwp/faq`.
3. Enable JSON-LD in **FAQ → Settings** (recommended for SEO) or per block.
4. Optionally complete setup to aggregate questions into a registry list.

JSON-LD on the front end does not require the registry. The registry is for browsing, reuse tracking, and future features.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/4wp-faq/` or install from the Plugins screen.
2. Activate **4WP FAQ**.
3. Add or convert Accordion blocks, then open **FAQ → Settings** for JSON-LD and registry options.

== Frequently Asked Questions ==

= Does this replace the Accordion block? =

No. 4WP FAQ is a thin wrapper. Your theme and block styles stay the same.

= Is JSON-LD enabled by default? =

No. Turn it on under **FAQ → Settings** (site-wide) or enable it for individual blocks in the block sidebar.

= What happens if I reset setup? =

You can change registry slugs in the wizard again, but FAQ **categories are removed**. Existing registry posts remain on the previous post type until you complete setup and run a new scan.

== Screenshots ==

1. 4WP FAQ block wrapping core Accordion in the editor
2. Settings screen — overview stats, rescan, and JSON-LD toggle
3. Block sidebar — per-block JSON-LD override

== Changelog ==

= 1.0.0 =
* Initial release: `forwp/faq` wrapper, setup wizard, registry scan, JSON-LD, admin settings UI.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
