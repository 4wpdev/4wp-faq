# Block structure (4WP FAQ)

Для агента: тут повний інвентар блоків/шорткодів цього плагіна; атрибути звіряй з block.json; для рендеру поза редактором — render_callback викликається напряму.

4WP FAQ does **not** replace core blocks. It adds a thin **`forwp/faq`** wrapper so schema, aggregation, and the FAQ registry can read your existing layout.

## Shortcodes

| Tag | Block analog |
|-----|----------------|
| `[forwp_faq_count]` | `forwp/faq-count` |

List/Categories — block-only, немає шорткод-аналога.

Source: `includes/class-display-blocks.php` (`add_shortcode( 'forwp_faq_count', … )`). No attributes.

## Attributes (from block.json)

Copy of `attributes` in each `block.json`. Do not invent keys.

### `forwp/faq` — `block.json`

| Attribute | Type | Default | Items |
|-----------|------|---------|-------|
| `jsonLd` | string | `""` | |
| `categoryMode` | string | `"none"` | |
| `categoryTermIds` | array | `[]` | number |
| `categoryTermId` | number | `0` | |
| `categoryName` | string | `""` | |

`jsonLd`: `""` (site default), `"enable"`, `"disable"`. `categoryMode`: `"none"`, `"existing"`, `"new"`.

### `forwp/faq-list` — `blocks/faq-list/block.json`

| Attribute | Type | Default | Items |
|-----------|------|---------|-------|
| `layout` | string | `"grouped"` | |
| `includeTermIds` | array | `[]` | number |
| `excludeTermIds` | array | `[]` | number |
| `cardDisplayMode` | string | `"accordion"` | |
| `cardShowSources` | boolean | `false` | |
| `cardSourcesLabel` | string | `"Used in"` | |
| `cardShowPostType` | boolean | `false` | |
| `cardQuestionStyle` | object | `{}` | |
| `cardAnswerStyle` | object | `{}` | |

`layout`: `"grouped"` \| `"flat"`. Inner block: `forwp/faq-card`.

### `forwp/faq-card` — `blocks/faq-card/block.json`

Parent: `forwp/faq-list`. `inserter: false`. `usesContext`: `postId`, `postType`.

| Attribute | Type | Default |
|-----------|------|---------|
| `displayMode` | string | `"accordion"` |
| `showSources` | boolean | `false` |
| `sourcesLabel` | string | `"Used in"` |
| `showPostType` | boolean | `false` |
| `questionStyle` | object | `{}` |
| `answerStyle` | object | `{}` |

`displayMode`: `"accordion"` \| `"heading"`.

### `forwp/faq-categories` — `blocks/faq-categories/block.json`

| Attribute | Type | Default | Items |
|-----------|------|---------|-------|
| `orientation` | string | `"vertical"` | |
| `label` | string | `"Categories"` | |
| `showAll` | boolean | `true` | |
| `allLabel` | string | `"All categories"` | |
| `showCount` | boolean | `true` | |
| `collapseChildren` | boolean | `true` | |
| `seoUrls` | boolean | `false` | |
| `includeTermIds` | array | `[]` | number |
| `excludeTermIds` | array | `[]` | number |

`orientation`: `"vertical"` \| `"horizontal"`.

### `forwp/faq-count` — `blocks/faq-count/block.json`

No `attributes` in `block.json`.

## Render outside the editor (`render_block()`)

Dynamic blocks call `render_callback` on the server. Use this from a theme template or custom CPT template (no editor, no `post_content`).

```php
echo render_block(
	array(
		'blockName' => 'forwp/faq-categories',
		'attrs'     => array(
			'orientation'      => 'vertical',
			'label'            => 'Categories',
			'showAll'          => true,
			'allLabel'         => 'All categories',
			'showCount'        => true,
			'collapseChildren' => true,
			'seoUrls'          => false,
			'includeTermIds'   => array(),
			'excludeTermIds'   => array(),
		),
	)
);

echo render_block(
	array(
		'blockName' => 'forwp/faq-count',
		'attrs'     => array(),
	)
);

echo render_block(
	array(
		'blockName'   => 'forwp/faq-list',
		'attrs'       => array(
			'layout'            => 'grouped',
			'includeTermIds'    => array(),
			'excludeTermIds'    => array(),
			'cardDisplayMode'   => 'accordion',
			'cardShowSources'   => false,
			'cardSourcesLabel'  => 'Used in',
			'cardShowPostType'  => false,
			'cardQuestionStyle' => array(),
			'cardAnswerStyle'   => array(),
		),
		'innerBlocks' => array(
			array(
				'blockName' => 'forwp/faq-card',
				'attrs'     => array(
					'displayMode'   => 'accordion',
					'showSources'   => false,
					'sourcesLabel'  => 'Used in',
					'showPostType'  => false,
					'questionStyle' => array(),
					'answerStyle'   => array(),
				),
			),
		),
	)
);
```

## Recommended (WordPress 6.x — core Accordion)

This is the **modern** pattern (same as in the block editor screenshot: List View → Group → 4WP FAQ → Accordion → Accordion Item).

```
forwp/faq                    ← 4WP FAQ wrapper (Convert to FAQ)
└── core/accordion           ← Accordion (container for all Q&A)
    └── core/accordion-item  ← Accordion Item (one question + answer)
        ├── core/accordion-heading   ← question (title)
        └── core/accordion-panel     ← answer (paragraphs, lists, etc.)
```

**Editor flow**

1. Add **Accordion** and **Accordion Item** blocks (or select an existing Accordion).
2. Use **Convert to FAQ** on the Accordion or Accordion Item toolbar, or **Transform to → 4WP FAQ**. The plugin inserts `forwp/faq` above and keeps your markup.
3. You can also select one or more **Details** or **Accordion Item** blocks (or heading + paragraph pairs) and convert them in one step.
4. Each **Accordion Item** (or Details block) becomes one FAQ entry for JSON-LD and (when enabled) the FAQ registry scan.

**Also accepted**

- `core/accordion-group` — can be transformed into `forwp/faq` + `core/accordion` like a plain Accordion.
- Multiple `core/details` — wrapped as-is inside `forwp/faq`.
- Multiple `forwp/faq` blocks on one page are allowed (e.g. “FAQ” and “Faq 2” sections).

## Legacy / alternative: Details

```
forwp/faq
└── core/details    ← single or multiple Details blocks (older pattern)
```

Still supported for schema and scan. Prefer **Accordion + Accordion Item** for new content.

## What the plugin reads as one FAQ item

| Source block | Question | Answer |
|--------------|----------|--------|
| `core/accordion-item` | Heading / `title` attribute / first inner block | Remaining inner blocks (panel content) |
| `core/details` | `summary` / title attribute | Content inside Details |

## Blocks that are not FAQ items by themselves

- `core/accordion` — container only; items are **`core/accordion-item`** children.
- `forwp/faq` — wrapper only; no Q&A text of its own.

## Display blocks (registry hub)

These are **not** wrappers around Accordion. After registry setup they query the FAQ CPT and taxonomy.

```
forwp/faq-categories
forwp/faq-list
└── forwp/faq-card
```

| Block | Name | Notes |
|---|---|---|
| 4WP FAQ List | `forwp/faq-list` | Grouped or flat. Include/exclude are **shared** with Categories on the same page. |
| 4WP FAQ Card | `forwp/faq-card` | Parent: List only. Accordion or heading; optional sources; Question / Answer typography (font, size, weight, color). |
| 4WP FAQ Categories | `forwp/faq-categories` | Nav + Interactivity filter. **All categories** = full synced list (editable label). Subcategories collapse by default (block setting); visitor expand/collapse is stored in the browser. |

PHP: `includes/class-display-blocks.php`, `includes/class-registry-content.php`, `includes/class-faq-filter.php`. Editor: `src/faq-list/`, `src/faq-card/`, `src/faq-categories/`, `src/display/sync-filters.js`. Front-end store: `assets/faq-view.js`. On-page title/description swap: `includes/class-faq-terms.php`.

## Hub template (pretty category URLs)

On `/hub/term-slug/` the plugin fills **native theme blocks** (no extra classes, no extra FAQ blocks). Heading level is the template’s choice.

**Title** (Display title, or category name if empty):

- `core/post-title` — Title (page / post)
- `core/query-title` — Query Title / Archive title (`type: archive`)
- `core/term-name` — Term Name

**Description** (native category Description field):

- `core/post-excerpt` — Excerpt (the hub page’s excerpt, not items in a Query Loop)
- `core/term-description` — Term Description (empty on `/hub/`, filled on `/hub/term-slug/`)

`core/heading` is not swapped.

On a pretty category URL the List does not output a group H2 for the active category (the page title already shows it). Child group titles stay.

**Canonical / social:** `rel=canonical` and `og:url` are `/hub/term-slug/`. Term **SEO image** is used for Open Graph / Twitter; empty falls back to the hub page image.

## Core Search (`core/search`)

On the same page as **4WP FAQ List**, add a core **Search** block. In the inspector: **4WP FAQ → Filter 4WP FAQ List**.

- Filters visible cards through the Interactivity store (`forwp/faq`). Does **not** submit a WordPress search.
- **4WP FAQ Count** follows the same query.
- Leave the toggle off for a normal site search.

## Related code

- Editor transforms & “Convert to FAQ”: `src/index.js`
- Server-side extraction: `includes/class-plugin.php` (`is_faq_item_block`, `extract_item_texts`)
