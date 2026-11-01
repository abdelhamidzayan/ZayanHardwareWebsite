# Zayan Hardware — Brand Identity

## Brand direction

Zayan Hardware is an Arabic-first technical community for mobile-phone hardware technicians. The visual identity should feel practical, confident, welcoming, and workshop-rooted—not like a generic dark dashboard or a consumer shopping app.

The interface uses a warm cream canvas, deep workshop green for identity and navigation, and restrained color accents for actions and technical categorization. Keep content, schematics, measurements, and repair evidence visually dominant.

## Core palette

| Token | Color | Hex | Intended use |
|---|---|---|---|
| Primary / Workshop Green | <span style="color:#105023">●</span> | `#105023` | Brand mark, navigation, links, selected states |
| Cream | | `#FFFCF3` | Main page background |
| Ivory | | `#F8F3E0` | Input surfaces, soft panels |
| Charcoal | | `#333C37` | Main text |
| Gray | | `#74766E` | Secondary text |
| Cyan | | `#98D7DF` | Informational accents and technical highlights |
| Yellow | | `#E0B718` | Attention and category accents |
| Orange | | `#D75421` | High-priority calls to action |
| Purple | | `#887F9E` | Secondary category accents |

## Semantic roles

- **Primary green:** identity, links, navigation, focus and selected states.
- **Orange CTA:** the strongest action on a screen, such as account creation or publishing. Keep the supplied orange as the button surface and use a near-black label for WCAG AA text contrast; use a deeper orange for orange-colored text.
- **Cyan:** informational or measurement-related accents.
- **Yellow:** caution/attention and selected category indicators; do not use it for body text on cream.
- **Purple:** secondary labels or category accents.
- **Success and danger:** retain clear semantic meaning and sufficient contrast; do not recolor status meaning just to use the palette.
- **Accessibility variants:** muted text uses a darker gray than the decorative palette gray; semantic soft surfaces and ink colors are centralized in tokens.

## Surface and component rules

- Use cream for the page canvas and near-white for cards; ivory is for fields and subtle grouped areas.
- Keep borders warm and quiet. Avoid heavy outlines and excessive shadows.
- Use green for active navigation and interactive text; use orange sparingly for primary conversion actions.
- Keep technical values, part numbers, measurements, and model identifiers in the existing LTR monospace treatment.
- Preserve the existing responsive layout, touch targets, focus visibility, reduced-motion behavior, and RTL logical properties.
- Do not introduce gradients or use all accent colors in one component. A screen should have one dominant brand color and at most one or two supporting accents.

## Implementation source

The canonical CSS tokens live in `public/css/tokens.css`. Components should consume semantic variables rather than repeat palette hex values. Browser/PWA theme colors and launcher icons should use the same primary green and cream background.

| Variable | Value |
|---|---|
| `--color-primary` | `#105023` |
| `--color-primary-hover` | `#0B3D1A` |
| `--color-primary-soft` | `#EAF1E8` |
| `--color-cta` | `#D75421` |
| `--color-cta-hover` | `#B94418` |
| `--color-cta-ink` | `#111111` |
| `--color-cta-text` | `#A83D18` |
| `--color-accent-cyan` | `#98D7DF` |
| `--color-accent-yellow` | `#E0B718` |
| `--color-accent-purple` | `#887F9E` |
| `--color-bg` | `#FFFCF3` |
| `--color-field` | `#F8F3E0` |
| `--color-ink` | `#333C37` |
| `--color-muted` | `#6B6D65` |
| `--color-brand-gray` | `#74766E` |

This guide describes the intended system. It does not claim that every legacy hard-coded color or every screen has already been migrated.
