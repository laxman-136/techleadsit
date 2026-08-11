# Design System Inspired by 3-Day Rapid Sales Sprint Bootcamp

> Auto-extracted from `https://webinar.rapidsalessprint.com/3-day-rapid-sales-sprint-bootcamp/` on 2026-07-17

## 1. Visual Theme & Atmosphere

Friendly, approachable design with rounded shapes and generous whitespace.

The hero section leads with "Join the 3-Day Rapid Sales Sprint Bootcamp  and Never Compete on Prices Again!".

**Key Characteristics:**
- Outfit as the heading font (custom web font loaded via @font-face)
- Manrope as the body font for all running text
- Heading weight 700, letter-spacing -0.1px
- Light/white background (#ffffff) as the primary canvas
- Primary accent `#f1b61a` used for CTAs and brand highlights
- 5 shadow level(s) detected — tinted shadows
- Rounded corners (16px+) creating a friendly, approachable feel
- Tags: light, rounded, colorful, sans-serif

## 2. Color Palette & Roles

### Primary
- **Primary Accent** (`#f1b61a`) · `--color-primary`: Brand color, CTA backgrounds, link text, interactive highlights.
- **Secondary Accent** (`#d41816`) · `--color-secondary`: Secondary brand, hover states, complementary highlights.
- **Background** (`#ffffff`) · `--color-bg`: Page background, primary canvas.
- **Background Secondary** (`#ecf2fc`) · `--color-bg-secondary`: Cards, surfaces, alternating sections.

### Text
- **Text Primary** (`#111111`) · `--color-text`: Headings and body text.
- **Text Secondary** (`#666666`) · `--color-text-secondary`: Muted text, captions, placeholders.

### Borders & Surfaces
- **Border** (`#ecf2fc`) · `--color-border`: Dividers, outlines, input borders.

### Full Extracted Palette

| # | Hex | CSS Variable | Role | Area | Contrast |
|---|---|---|---|---|---|
| 1 | `#ffffff` | `--palette-1` | block | large | text-dark |
| 2 | `#ecf2fc` | `--palette-2` | block | large | text-dark |
| 3 | `#f1b61a` | `--palette-3` | block | large | text-dark |
| 4 | `#000000` | `--palette-4` | block | large | text-light |
| 5 | `#2f5385` | `--palette-5` | block | large | text-light |
| 6 | `#d41816` | `--palette-6` | button | medium | text-light |
| 7 | `#27b376` | `--palette-7` | button | medium | text-dark |
| 8 | `#ff0000` | `--palette-8` | text-accent | small | text-light |
| 9 | `#1db082` | `--palette-9` | text-accent | small | text-light |

## 3. Typography Rules

- **Heading Font:** `Outfit` (web font)
- **Body Font:** `Manrope` (web font)

### Type Hierarchy

| Role | Font | Size | Weight | Line Height | Letter Spacing |
|---|---|---|---|---|---|
| H1 | Outfit | 52px | 700 | 67.6px | -0.1px |
| H2 | Outfit | 20px | 500 | 26px | -0.1px |
| H3 | Outfit | 17px | 500 | 22.1px | -0.1px |
| H4 | Outfit | 24px | 700 | 33.6px | -0.1px |
| Body | Outfit | 20px | 600 | 20px | -0.1px |

### Type Scale

| Token | Size | Suggested Usage |
|---|---|---|
| Display | `62px` | headings |
| H1 | `52px` | headings |
| H2 | `42px` | headings |
| H3 | `40px` | headings |
| H4 | `36px` | headings |
| Body L | `33px` | body / supporting text |
| Body | `32px` | body / supporting text |
| Small | `28px` | body / supporting text |
| XS | `27px` | body / supporting text |
| Caption | `26px` | body / supporting text |

## 4. Component Stylings

### Primary Button

```css
.btn-primary {
  background: transparent;
  color: #111111;
  border-radius: 0px;
  padding: 0px 0px;
  font-size: 22px;
  font-weight: 300;
  border: none;
  cursor: pointer;
}
```

### Filled Button

```css
.btn-filled {
  background: #f1b61a;
  color: #000000;
  border-radius: 16px;
  padding: 28px 20px;
  font-size: 21px;
  font-weight: 600;
  border: none;
  cursor: pointer;
}
```

### Ghost Button

```css
.btn-ghost {
  background: transparent;
  color: #000000;
  border-radius: 0px;
  padding: 0px 0px;
  font-size: 18px;
  font-weight: 400;
  border: none;
  cursor: pointer;
}
```

### Filled Button 2

```css
.btn-filled-2 {
  background: #27b376;
  color: #ffffff;
  border-radius: 5px;
  padding: 12px 12px;
  font-size: 16px;
  font-weight: 600;
  border: none;
  cursor: pointer;
}
```

### Filled Button 3

```css
.btn-filled-3 {
  background: #2f5385;
  color: #111111;
  border-radius: 0px;
  padding: 20px 20px;
  font-size: 22px;
  font-weight: 700;
  border: none;
  cursor: pointer;
}
```

### Filled Button 4

```css
.btn-filled-4 {
  background: #ffffff;
  color: #111111;
  border-radius: 0px;
  padding: 20px 20px;
  font-size: 22px;
  font-weight: 700;
  border: none;
  cursor: pointer;
}
```

### Card

```css
.card {
  background: #ffffff;
  border-radius: 16px;
  padding: 20px;
  box-shadow: rgba(0, 0, 0, 0.15) 0px 0px 10px 0px;
}
```

## 5. Layout Principles

- **Base spacing unit:** `10px` — use multiples (20px, 30px, 40px, etc.)

### Spacing Scale (extracted from real elements)

| Token | Value | Role |
|---|---|---|
| spacing-1 | `10px` | element |
| spacing-2 | `16px` | element |
| spacing-3 | `15px` | element |
| spacing-4 | `5px` | element |
| spacing-5 | `28px` | card |
| spacing-6 | `48px` | card |
| spacing-7 | `12px` | element |
| spacing-8 | `2px` | element |

### Border Radius Scale

| Token | Value | Element |
|---|---|---|
| radius-card | `16px` | card |
| radius-card | `20px` | card |
| radius-button | `10px` | button |
| radius-subtle | `5px` | subtle |
| radius-button | `14px` | button |
| radius-button | `9px` | button |

## 6. Depth & Elevation

| Level | Shadow | Usage |
|---|---|---|
| Deep | `rgba(0, 0, 0, 0.15) 0px 0px 30px 0px` | Hero sections, deep layers |
| Mid | `rgba(0, 0, 0, 0.15) 0px 0px 10px 0px` | Dropdowns, popovers |
| Mid | `rgba(0, 0, 0, 0.05) 0px 0px 10px 0px` | Dropdowns, popovers |
| Deep | `rgba(0, 0, 0, 0.15) 0px 0px 25px 0px` | Hero sections, deep layers |
| Mid | `rgba(47, 83, 133, 0.31) 0px 0px 10px 0px` | Dropdowns, popovers |

> **Note:** This site uses chromatic (color-tinted) shadows rather than pure black — this is a deliberate brand choice that adds warmth to elevation.

## 7. Do's and Don'ts

### Do
- Use `#ffffff` as the primary background color
- Use `Outfit` for all headings and `Manrope` for body text
- Use `#f1b61a` as the single dominant accent/CTA color
- Maintain `10px` as the base spacing unit — all gaps should be multiples
- Use rounded corners (`16px`+) consistently for all interactive elements
- Embrace bold color combinations — playful energy is the point
- Apply the shadow system for elevation — use the extracted shadow values
- Use weight 700 for headings to match the brand's typographic voice

### Don't
- Don't use colors outside the extracted palette without justification
- Don't substitute Outfit/Manrope with generic alternatives
- Don't use irregular spacing — stick to 10px grid
- Don't use dark/black backgrounds — this is a light-themed design
- Don't use sharp corners — they feel hostile in this rounded design language
- Don't use pure black (#000000) for text — use `#111111` instead
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
- Maintain 10px base unit across breakpoints — only scale multipliers

## 9. Agent Prompt Guide

### Quick Color Reference

```
Background:  #ffffff
Text:        #111111
Accent:      #f1b61a
Secondary:   #d41816
Border:      #ecf2fc
```

### Example Prompts

1. "Build a hero section with a `#ffffff` background, `Outfit` heading in `#111111`, and a `#f1b61a` CTA button with 16px radius."
2. "Create a pricing card using background `#ecf2fc`, border `#ecf2fc`, `Manrope` for text, and 30px padding."
3. "Design a navigation bar — `#ffffff` background, `#111111` links, `#f1b61a` for active state."
4. "Build a feature grid with 3 columns, 30px gap, each card using the card component style."
5. "Create a footer with `#111111` background, `#ffffff` text, and 20px padding."

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

> 75 custom properties extracted from `:root` / `html` stylesheets.

### Color Variables

| Variable | Value |
|---|---|
| `--wp-admin-theme-color` | `#007cba` |
| `--wp-admin-theme-color-darker-10` | `#006ba1` |
| `--wp-admin-theme-color-darker-20` | `#005a87` |
| `--wp-block-synced-color` | `#7a00df` |
| `--wp--preset--color--black` | `#000000` |
| `--wp--preset--color--cyan-bluish-gray` | `#abb8c3` |
| `--wp--preset--color--white` | `#ffffff` |
| `--wp--preset--color--pale-pink` | `#f78da7` |
| `--wp--preset--color--vivid-red` | `#cf2e2e` |
| `--wp--preset--color--luminous-vivid-orange` | `#ff6900` |
| `--wp--preset--color--luminous-vivid-amber` | `#fcb900` |
| `--wp--preset--color--light-green-cyan` | `#7bdcb5` |
| `--wp--preset--color--vivid-green-cyan` | `#00d084` |
| `--wp--preset--color--pale-cyan-blue` | `#8ed1fc` |
| `--wp--preset--color--vivid-cyan-blue` | `#0693e3` |
| `--wp--preset--color--vivid-purple` | `#9b51e0` |
| `--wp--preset--color--base` | `#FFFFFF` |
| `--wp--preset--color--contrast` | `#111111` |
| `--wp--preset--color--accent-1` | `#FFEE58` |
| `--wp--preset--color--accent-2` | `#F6CFF4` |
| `--wp--preset--color--accent-3` | `#503AA8` |
| `--wp--preset--color--accent-4` | `#686868` |
| `--wp--preset--color--accent-5` | `#FBFAF3` |
| `--wp--preset--color--accent-6` | `color-mix(in srgb, currentColor 20%, transparent)` |
| `--wp--preset--gradient--vivid-cyan-blue-to-vivid-purple` | `linear-gradient(135deg,rgba(6,147,227,1) 0%,rgb(155,81,224) 100%)` |
| `--wp--preset--gradient--light-green-cyan-to-vivid-green-cyan` | `linear-gradient(135deg,rgb(122,220,180) 0%,rgb(0,208,130) 100%)` |
| `--wp--preset--gradient--luminous-vivid-amber-to-luminous-vivid-orange` | `linear-gradient(135deg,rgba(252,185,0,1) 0%,rgba(255,105,0,1) 100%)` |
| `--wp--preset--gradient--luminous-vivid-orange-to-vivid-red` | `linear-gradient(135deg,rgba(255,105,0,1) 0%,rgb(207,46,46) 100%)` |
| `--wp--preset--gradient--very-light-gray-to-cyan-bluish-gray` | `linear-gradient(135deg,rgb(238,238,238) 0%,rgb(169,184,195) 100%)` |
| `--wp--preset--gradient--cool-to-warm-spectrum` | `linear-gradient(135deg,rgb(74,234,220) 0%,rgb(151,120,209) 20%,rgb(207,42,186) 40%,rgb(238,44,130) 60%,rgb(251,105,98) 80%,rgb(254,248,76) 100%)` |
| ... | *(11 more)* |

### Spacing Variables

| Variable | Value |
|---|---|
| `--wp-admin-border-width-focus` | `2px` |
| `--wp--preset--aspect-ratio--square` | `1` |
| `--wp--preset--spacing--20` | `10px` |
| `--wp--preset--spacing--30` | `20px` |
| `--wp--preset--spacing--40` | `30px` |
| `--wp--style--global--content-size` | `645px` |
| `--wp--style--global--wide-size` | `1340px` |
| `--wp--style--block-gap` | `1.2rem` |
| `--direction-multiplier` | `1` |

### Typography Variables

| Variable | Value |
|---|---|
| `--wp--preset--font-size--normal` | `16px` |
| `--wp--preset--font-size--huge` | `42px` |
| `--wp--preset--font-size--small` | `0.875rem` |
| `--wp--preset--font-size--medium` | `clamp(1rem, 1rem + ((1vw - 0.2rem) * 0.196), 1.125rem)` |
| `--wp--preset--font-size--large` | `clamp(1.125rem, 1.125rem + ((1vw - 0.2rem) * 0.392), 1.375rem)` |
| `--wp--preset--font-size--x-large` | `clamp(1.75rem, 1.75rem + ((1vw - 0.2rem) * 0.392), 2rem)` |
| `--wp--preset--font-size--xx-large` | `clamp(2.15rem, 2.15rem + ((1vw - 0.2rem) * 1.333), 3rem)` |
| `--wp--preset--font-family--manrope` | `Manrope, sans-serif` |
| `--wp--preset--font-family--fira-code` | `"Fira Code", monospace` |

### Other Variables

| Variable | Value |
|---|---|
| `--wp-admin-theme-color--rgb` | `0,124,186` |
| `--wp-admin-theme-color-darker-10--rgb` | `0,107,161` |
| `--wp-admin-theme-color-darker-20--rgb` | `0,90,135` |
| `--wp-block-synced-color--rgb` | `122,0,223` |
| `--wp-bound-block-color` | `var(--wp-block-synced-color)` |
| `--wp--preset--aspect-ratio--4-3` | `4/3` |
| `--wp--preset--aspect-ratio--3-4` | `3/4` |
| `--wp--preset--aspect-ratio--3-2` | `3/2` |
| `--wp--preset--aspect-ratio--2-3` | `2/3` |
| `--wp--preset--aspect-ratio--16-9` | `16/9` |
| `--wp--preset--aspect-ratio--9-16` | `9/16` |
| `--wp--preset--spacing--50` | `clamp(30px, 5vw, 50px)` |
| `--wp--preset--spacing--60` | `clamp(30px, 7vw, 70px)` |
| `--wp--preset--spacing--70` | `clamp(50px, 7vw, 90px)` |
| `--wp--preset--spacing--80` | `clamp(70px, 10vw, 140px)` |
| ... | *(1 more)* |
