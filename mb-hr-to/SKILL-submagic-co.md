---
name: design-submagic-co
description: Design system extracted from Submagic (https://www.submagic.co/). Use when building UI that should match this brand's visual identity.
triggers:
  - "Submagic"
  - "submagic-co"
  - "design like Submagic"
  - "Submagic風"
source: https://www.submagic.co/
extractedAt: 2026-08-01T11:14:22.434Z
tags: ["light", "rounded", "accented", "sans-serif"]
---
# Design System Inspired by Submagic

> Auto-extracted from `https://www.submagic.co/` on 2026-08-01

## 1. Visual Theme & Atmosphere

Clean, minimal, and product-focused with deliberate use of whitespace.

The hero section leads with "Edit shorts 10x faster with AI" followed by "The new way to edit videos faster. From raw footage to viral shorts in 1 click.".

**Key Characteristics:**
- Inter as the heading font
- Inter as the body font for all running text
- Heading weight 600, letter-spacing -1.92px
- Light/white background (#ffffff) as the primary canvas
- Primary accent `#ff4f01` used for CTAs and brand highlights
- 8 shadow level(s) detected — tinted shadows
- Rounded corners (12px+) creating a friendly, approachable feel
- Tags: light, rounded, accented, sans-serif

## 2. Color Palette & Roles

### Primary
- **Primary Accent** (`#ff4f01`) · `--color-primary`: Brand color, CTA backgrounds, link text, interactive highlights.
- **Secondary Accent** (`#ff5100`) · `--color-secondary`: Secondary brand, hover states, complementary highlights.
- **Background** (`#ffffff`) · `--color-bg`: Page background, primary canvas.
- **Background Secondary** (`#141414`) · `--color-bg-secondary`: Cards, surfaces, alternating sections.

### Text
- **Text Primary** (`#5f5f6d`) · `--color-text`: Headings and body text.
- **Text Secondary** (`#666666`) · `--color-text-secondary`: Muted text, captions, placeholders.

### Borders & Surfaces
- **Border** (`#f9f9fb`) · `--color-border`: Dividers, outlines, input borders.

### Full Extracted Palette

| # | Hex | CSS Variable | Role | Area | Contrast |
|---|---|---|---|---|---|
| 1 | `#ffffff` | `--palette-1` | block | large | text-dark |
| 2 | `#141414` | `--palette-2` | section | large | text-light |
| 3 | `#ff4f01` | `--palette-3` | text-accent | medium | text-light |
| 4 | `#fff1f1` | `--palette-4` | button | small | text-dark |
| 5 | `#3f3c39` | `--palette-5` | badge | small | text-light |

## 3. Typography Rules

- **Heading Font:** `Inter`, sans-serif
- **Body Font:** `Inter`, sans-serif

### Type Hierarchy

| Role | Font | Size | Weight | Line Height | Letter Spacing |
|---|---|---|---|---|---|
| H1 | Inter | 48px | 600 | 52.8px | -1.92px |
| H2 | Inter | 38.4px | 600 | 46.08px | -1.152px |
| H3 | Inter | 15px | 600 | 22.5px | normal |
| H4 | Inter | 38px | 600 | 34px | -1.14px |
| Body | Inter | 14px | 700 | 20px | normal |

### Type Scale

| Token | Size | Suggested Usage |
|---|---|---|
| Display | `48px` | headings |
| H1 | `38.4px` | headings |
| H2 | `38px` | headings |
| H3 | `26.6667px` | headings |
| H4 | `19px` | headings |
| Body L | `18px` | body / supporting text |
| Body | `16.8px` | body / supporting text |
| Small | `16px` | body / supporting text |
| XS | `15px` | body / supporting text |
| Caption | `14.4px` | body / supporting text |

## 4. Component Stylings

### Primary Button

```css
.btn-primary {
  background: transparent;
  color: #6b7280;
  border-radius: 0px;
  padding: 0px 0px;
  font-size: 13.8px;
  font-weight: 500;
  border: none;
  cursor: pointer;
}
```

### Card

```css
.card {
  background: #f9f9fb;
  border-radius: 20px;
  padding: 8px;
  box-shadow: rgba(112, 122, 143, 0.12) 0px 1px 2px 0px, rgba(81, 88, 103, 0.08) 0px 1px 2px 0px, rgba(81, 88, 103, 0.1) 0px 0px 0px 1px;
}
```

## 5. Layout Principles

- **Base spacing unit:** `8px` — use multiples (16px, 24px, 32px, etc.)

### Spacing Scale (extracted from real elements)

| Token | Value | Role |
|---|---|---|
| spacing-1 | `8px` | element |
| spacing-2 | `9px` | element |
| spacing-3 | `24px` | card |
| spacing-4 | `12px` | element |
| spacing-5 | `18px` | element |
| spacing-6 | `64px` | section |
| spacing-7 | `6px` | element |
| spacing-8 | `20px` | element |

### Border Radius Scale

| Token | Value | Element |
|---|---|---|
| radius-button | `12px` | button |
| radius-button | `10px` | button |
| radius-card | `20px` | card |
| radius-card | `16px` | card |
| radius-subtle | `4px` | subtle |
| radius-button | `8px` | button |

## 6. Depth & Elevation

| Level | Shadow | Usage |
|---|---|---|
| Low | `rgba(112, 122, 143, 0.12) 0px 1px 2px 0px, rgba(81, 88, 103, 0.08) 0px 1px 2px 0...` | Cards, subtle elevation |
| Low | `rgba(0, 0, 0, 0.06) 0px 0px 0px 1px inset` | Cards, subtle elevation |
| High | `rgba(0, 0, 0, 0.08) 0px 2px 13px 0px` | Modals, floating elements |
| Mid | `rgba(14, 56, 107, 0.15) 0px 4px 8px 0px` | Dropdowns, popovers |
| Low | `rgba(0, 0, 0, 0.12) 0px 0px 0px 2px inset, rgba(255, 255, 255, 0.75) 0px 0px 10p...` | Cards, subtle elevation |

> **Note:** This site uses chromatic (color-tinted) shadows rather than pure black — this is a deliberate brand choice that adds warmth to elevation.

## 7. Do's and Don'ts

### Do
- Use `#ffffff` as the primary background color
- Use `Inter` for all headings and `Inter` for body text
- Use `#ff4f01` as the single dominant accent/CTA color
- Maintain `8px` as the base spacing unit — all gaps should be multiples
- Use rounded corners (`12px`+) consistently for all interactive elements
- Apply the shadow system for elevation — use the extracted shadow values
- Use weight 600 for headings to match the brand's typographic voice

### Don't
- Don't use colors outside the extracted palette without justification
- Don't substitute Inter/Inter with generic alternatives
- Don't use irregular spacing — stick to 8px grid
- Don't use dark/black backgrounds — this is a light-themed design
- Don't use sharp corners — they feel hostile in this rounded design language
- Don't use pure black (#000000) for text — use `#5f5f6d` instead
- Don't add decorative elements not present in the original design — no badges, ribbons, banners, or ornaments unless the source site uses them
- Don't invent UI patterns the source site doesn't have — if the original has no NEW badge, don't add one just because a red is in the palette

## 8. Responsive Behavior

| Breakpoint | Width | Notes |
|---|---|---|
| Mobile | < 640px | Single column, stack sections, reduce font sizes ~80% |
| Tablet | 640–1024px | 2-column where appropriate, maintain spacing ratios |
| Desktop | 1024–1440px | Full layout as designed |
| Wide | > 1440px | Max-width container, center content |

- Touch targets: minimum 44×44px on mobile
- Maintain 8px base unit across breakpoints — only scale multipliers

## 9. Agent Prompt Guide

### Quick Color Reference

```
Background:  #ffffff
Text:        #5f5f6d
Accent:      #ff4f01
Secondary:   #ff5100
Border:      #f9f9fb
```

### Example Prompts

1. "Build a hero section with a `#ffffff` background, `Inter` heading in `#5f5f6d`, and a `#ff4f01` CTA button."
2. "Create a pricing card using background `#141414`, border `#f9f9fb`, `Inter` for text, and 24px padding."
3. "Design a navigation bar — `#ffffff` background, `#5f5f6d` links, `#ff4f01` for active state."
4. "Build a feature grid with 3 columns, 24px gap, each card using the card component style."
5. "Create a footer with `#5f5f6d` background, `#ffffff` text, and 16px padding."

### Iteration Guide

1. Start with layout structure (sections, grid, spacing)
2. Apply colors from the palette — background first, then text, then accents
3. Set typography — font families, sizes from the type scale, weights
4. Add components — buttons, cards, inputs using the specs above
5. Apply border-radius consistently across all elements
6. Add shadows for depth — use the extracted shadow values, not defaults
7. Check responsive behavior — test mobile and tablet layouts
8. Final pass — verify all colors match, spacing is consistent, fonts are correct

## 10. CSS Custom Properties

> 55 custom properties extracted from `:root` / `html` stylesheets.

### Color Variables

| Variable | Value |
|---|---|
| `--dark-gray` | `#5f5f6d` |
| `--orange-red<deleted|variable-b657f39a>` | `#ff4f01` |
| `--green--base<deleted|variable-25c7bbe6>` | `#c2ff66` |
| `--gray--gray-900<deleted|variable-e0ae2212>` | `#18181b` |
| `--gray--gray-700<deleted|variable-ddbf9f05>` | `#4d4d56` |
| `--gray--gray-200<deleted|variable-d33d3cd0>` | `#ececee` |
| `--gray--gray-100<deleted|variable-5168063f>` | `#f7f7f8` |
| `--gray--gray-300<deleted|variable-2fb83540>` | `#e4e4e7` |
| `--light-gray` | `#f7f7f7` |
| `--black` | `#000` |
| `--gray` | `#aaaeb3` |
| `--gray--gray-500<deleted|variable-faec9ec1>` | `#9e9ea9` |
| `--gray--gray-800<deleted|variable-f280466c>` | `#3a3a41` |
| `--gray--gray-600<deleted|variable-746d9ade>` | `#696977` |
| `--gainsboro<deleted|variable-888cf4e6>` | `#e5e7eb` |
| `--white-smoke<deleted|variable-857b18ef>` | `#f3f4f6` |
| `--submagic-orange` | `#ff4f01` |
| `--accessible-components--dodger-blue` | `#1378d1` |
| `--slate-grey<deleted|variable-5e90ecb1>` | `#6b7280` |
| `--conversion-flow-library-medium-purple<deleted|variable-37794173>` | `#b16cea` |
| `--red<deleted|variable-050809eb>` | `#cc3d00` |
| `--dark-grey<deleted|variable-35b7c2bc>` | `#afafaf14` |
| `--white-smoke-2<deleted|variable-32b5f6f8>` | `#f1f1f1` |
| `--blue-black<deleted|variable-c1afc500>` | `#111827` |
| `--untitled-ui-gray200<deleted|variable-bad2ea62>` | `#eaecf0` |
| `--untitled-ui-gray900<deleted|variable-f63dbb51>` | `#101828` |
| `--untitled-ui-gray100<deleted|variable-4a47ec41>` | `#f2f4f7` |
| `--untitled-ui-gray700<deleted|variable-d4f37b50>` | `#344054` |
| `--floral-white<deleted|variable-bbe37a6c>` | `#fff7ed` |
| `--untitled-ui-gray500<deleted|variable-68f29e84>` | `#667085` |
| ... | *(13 more)* |

### Spacing Variables

| Variable | Value |
|---|---|
| `--apollo-sidebar-button-size` | `40px` |
| `--apollo-sidebar-icon-size` | `20px` |
| `--apollo-sidebar-hover-opacity` | `0.8` |
| `--apollo-sidebar-z-input` | `2` |
| `--apollo-sidebar-z-icon` | `1` |

### Other Variables

| Variable | Value |
|---|---|
| `--neutral--black<deleted|variable-052c90de>` | `black` |
| `--black<deleted|variable-7d0ceb88>` | `black` |
| `--gray--white<deleted|variable-6c8c2f79>` | `white` |
| `--white` | `white` |
| `--alley-component-library--neutral--black` | `black` |
| `--button-second-pricing<deleted|variable-0ec34ddb-13a9-43c4-0147-6724a706d5c5>` | `var(--gray--white\<deleted\|variable-6c8c2f79\>)` |
| `--apollo-sidebar-transition` | `all 0.2s ease` |
