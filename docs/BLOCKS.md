# Block structure (4WP FAQ)

4WP FAQ does **not** replace core blocks. It adds a thin **`forwp/faq`** wrapper so schema, aggregation, and the FAQ registry can read your existing layout.

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
2. Use **Convert to FAQ** on the Accordion or Accordion Item toolbar — the plugin inserts `forwp/faq` above and keeps your markup.
3. Each **Accordion Item** becomes one FAQ entry for JSON-LD and (when enabled) the FAQ registry scan.

**Also accepted**

- `core/accordion-group` — can be transformed into `forwp/faq` + `core/accordion` like a plain Accordion.
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

## Related code

- Editor transforms & “Convert to FAQ”: `src/index.js`
- Server-side extraction: `includes/class-plugin.php` (`is_faq_item_block`, `extract_item_texts`)
