---
name: Moorelife Resort / HotelMS
description: A comprehensive hotel operations platform with a polished public brand surface
colors:
  primary: "#4f46e5"
  primary-subtle: "#6366f1"
  ink: "#0f172a"
  ink-muted: "#475569"
  ink-dim: "#94a3b8"
  surface: "#ffffff"
  surface-alt: "#f8fafc"
  surface-raised: "#ffffff"
  border: "#e2e8f0"
  border-strong: "#cbd5e1"
  success: "#10b981"
  danger: "#dc2626"
  whatsapp: "#059669"
  header-initial: "transparent"
  header-scrolled: "rgba(255,255,255,0.95)"
typography:
  display:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(2.5rem, 6vw, 4.5rem)"
    fontWeight: 900
    lineHeight: 1
    letterSpacing: "-0.03em"
  headline:
    fontSize: "clamp(1.5rem, 4vw, 2.5rem)"
    fontWeight: 800
    lineHeight: 1.15
    letterSpacing: "-0.02em"
  title:
    fontSize: "1.125rem"
    fontWeight: 700
    lineHeight: 1.3
  body:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.9375rem"
    fontWeight: 400
    lineHeight: 1.65
    letterSpacing: "normal"
  label:
    fontSize: "0.625rem"
    fontWeight: 900
    lineHeight: 1.2
    letterSpacing: "0.2em"
    textTransform: "uppercase"
rounded:
  sm: "0.5rem"
  md: "0.75rem"
  lg: "1rem"
  full: "9999px"
spacing:
  xs: "0.25rem"
  sm: "0.5rem"
  md: "1rem"
  lg: "1.5rem"
  xl: "2rem"
  "2xl": "3rem"
components:
  button-primary:
    backgroundColor: "{colors.ink}"
    textColor: "{colors.surface}"
    rounded: "{rounded.lg}"
    padding: "1rem 2rem"
  button-primary-hover:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.surface}"
    rounded: "{rounded.lg}"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    border: "1px solid {colors.border}"
  button-ghost:
    backgroundColor: "transparent"
    textColor: "{colors.ink-muted}"
    rounded: "{rounded.md}"
  input-default:
    backgroundColor: "{colors.surface-alt}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    border: "2px solid {colors.surface-alt}"
  input-focus:
    backgroundColor: "{colors.surface}"
    border: "2px solid {colors.primary}"
  card-default:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.md}"
  sidebar-default:
    backgroundColor: "{colors.surface}"
    border-right: "1px solid {colors.border}"
---

# Design System: Moorelife Resort / HotelMS

## 1. Overview

**Creative North Star: "The Hospitality Command Center"**

This is a system that balances two modes: the warm, cinematic confidence of a luxury resort's public face, and the quiet precision of a mission-control dashboard where staff run operations. The public pages feel aspirational — dark headers floating over hero imagery, smooth scroll, magnetic buttons, a glowing WhatsApp concierge. The staff and admin surfaces are clear, task-focused, and efficient: sidebars with dense nav, bordered panels, scannable table rows. They share the same bones — same font, same ink color, same corner language — but the saturation and density shift depending on who is looking.

**Key Characteristics:**
- Modern typographic confidence: one typeface (Figtree) at extreme weight contrast — black (900) for display, light (300) for body, and a crisp uppercase label system.
- Ink-driven: the primary dark is near-black slate-950, not navy or charcoal. The accent (indigo-600) is used sparingly — ≤10% of any screen — for focus rings, key CTAs, and hover transformations.
- Shadow as substance: shadows are used freely to separate surfaces. They are ambient and defined (8-20px blur), never decorative ghost-cards with borders.
- Two densities: brand pages breathe (loose spacing, large type, generous padding); product pages are tighter (compact nav, condensed tables, smaller inputs).
- Rejects: cream/sand/beige backgrounds, gradient text, numbered section markers, tiny uppercase eyebrows on every section, glassmorphism, side-stripe borders.

## 2. Colors

The palette is restrained by role but generous in application. One near-black ink, one indigo accent, one emerald for positive signals, and a family of slate neutrals.

### Primary
- **Command Ink** (`#0f172a` / slate-950): The primary dark for body text, primary buttons, footer backgrounds, and the login card icon. Not navy, not charcoal — a true deep slate. On the public site, it appears as the header text color and footer.
- **Signal Blue** (`#4f46e5` / indigo-600): The accent. Used for focus rings (`focus:ring-indigo-500`), hover state transformations (buttons shifting from ink to indigo), navigation link hover, and the "forgot password?" link. Below 10% surface coverage.
- **Signal Blue Soft** (`#6366f1` / indigo-500): The lighter sibling for focus ring halos and border treatments.

### Neutral
- **White** (`#ffffff`): Primary surface for cards, sidebars, modals, and content panels.
- **Alt Surface** (`#f8fafc` / slate-50): Used for page backgrounds, input backgrounds, and subtle container differentiation.
- **Border Light** (`#e2e8f0` / slate-200): Default border on cards, inputs, sidebars, and dividers.
- **Border Strong** (`#cbd5e1` / slate-300): Active borders, secondary button borders.
- **Ink Muted** (`#475569` / slate-600): Secondary body text, navigation labels, form labels.
- **Ink Dim** (`#94a3b8` / slate-400): Placeholder text, metadata, footer legal text.

### Semantic
- **Signal Green** (`#10b981` / emerald-500): Success states, confirmation badges, status indicators.
- **Signal Green Deep** (`#059669` / emerald-600): WhatsApp button, hover states for green elements.
- **Danger** (`#dc2626` / red-600): Destructive actions, error states, sign-out buttons.

### Named Rules
**The One Voice Rule.** The indigo accent covers ≤10% of any screen. Its rarity is the point — when indigo appears (a focus ring, a hover shift, a link), it means "this thing is interactive." Overuse would dull that signal.

**The Tint Rule.** Gray text on colored backgrounds is prohibited. On dark surfaces (footer, login icon), text is white. On tinted surfaces, use a darker shade of the surface's own hue, not a generic gray.

## 3. Typography

**Display & Body Font:** Figtree with system-ui sans-serif fallback. One family across all surfaces — the contrast comes from weight, size, and case, not a second font.

**Character:** Confident and architectural. Figtree at weight 900 reads as authoritative without being loud; at weight 300 it feels refined and airy. The uppercase label system (0.2em tracking, weight 900, 10px) is a structural motif — it appears above form fields, in nav links, and in footer headings. It's the system's consistent cadence.

### Hierarchy
- **Display** (900, `clamp(2.5rem, 6vw, 4.5rem)`, 1.0): Hero headlines on the public site. Letter-spacing floor of -0.03em (never tighter). `text-wrap: balance` applied.
- **Headline** (800, `clamp(1.5rem, 4vw, 2.5rem)`, 1.15): Section headings on brand pages, page titles in admin.
- **Title** (700, 1.125rem, 1.3): Card titles, modal headers, sidebar labels.
- **Body** (400, 0.9375rem, 1.65): Paragraph text. Max line length 65-75ch on prose. `text-wrap: pretty` for orphan control.
- **Label** (900, 0.625rem / 10px, 1.2, 0.2em tracking, uppercase): Form labels, nav links, footer headings, badge text. The system's architectural motif. Always uppercase at this tracking.

### Named Rules
**The Figtree-Only Rule.** No font pairing. Figtree at 300/400/700/900 covers the full range from editorial body to commanding display. A second font would compete with the weight contrast that is the system's voice.

**The Label Cadence Rule.** Labels are always 10px, weight 900, uppercase, 0.2em tracking. Inconsistent casing or weight in label positions is a system defect.

## 4. Elevation

This system uses shadows freely as a separation mechanism. Depth is conveyed through defined ambient shadows on interactive and elevated surfaces. The public brand pages use shadows more expressively (hero glow, WhatsApp pulse ring, card shadows); the product/admin surfaces use them more sparingly (dropdowns, modals, raised cards).

### Shadow Vocabulary
- **Surface Raised** (`0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06)`): Default card elevation. Used for summary cards, stat widgets, and content panels.
- **Dropdown** (`0 10px 15px rgba(0,0,0,0.1), 0 4px 6px rgba(0,0,0,0.05)`): Dropdown menus, popovers, datepickers.
- **Modal** (`0 20px 60px rgba(0,0,0,0.15)`): Modal backdrops and elevated overlays.
- **WhatsApp Glow** (`0 20px 50px rgba(5,150,105,0.3)`): Exclusive to the floating WhatsApp concierge button. Emerald-tinted.
- **Hero Glow** (`inset 0 0 120px rgba(0,0,0,0.4)`): Dark overlay on hero imagery for text legibility.
- **Button Hover** (`0 8px 25px rgba(0,0,0,0.12)`): Primary button hover state lift.

### Named Rules
**The Modal Rule.** Modals use a gray-500/75 (`bg-gray-500 bg-opacity-75`) backdrop. The backdrop is click-to-close. Escape key closes. `overflow: hidden` is set on body while modal is open.

**The Dropdown Escape Rule.** Dropdowns use a fixed-position overlay (`fixed inset-0 z-40`) to intercept outside clicks. This avoids `overflow: hidden` clipping issues with absolutely-positioned children.

## 5. Components

### Buttons
- **Shape:** All buttons are rounded. Primary buttons use `rounded-xl` (12px), secondary and ghost use `rounded-lg` (8px).
- **Primary:** Ink background (`bg-slate-950`), white text, `rounded-xl`, horizontal padding 2rem, vertical padding 1rem (`px-8 py-4`). On hover, background shifts to indigo-600 and a shadow lifts (`hover:bg-indigo-600`, `shadow-lg`). Active scale scoots in (`active:scale-[0.98]`). Transition on `background`, `transform`, and `shadow`.
- **Secondary:** White background, 1px slate-200 border, ink-muted text. Hover: `bg-gray-50`. Focus: 2px indigo ring.
- **Ghost/Text:** Transparent background, ink-muted text. Hover: text shifts to ink. Used for less prominent actions.
- **Disabled:** `opacity-50`, `cursor-not-allowed`. Primary's hover override is suppressed.
- **Uppercase label style is used on the public site CTA buttons** (`text-[10px] font-black uppercase tracking-widest`). For primary buttons, the entire label is uppercase.

### Chips / Status Badges
- **Style:** Small rounded pills (`rounded-full`), `text-xs font-bold`, uppercase tracking-wider. Background color maps to semantic state (emerald for success, amber for pending, red for error, slate for inactive). Text uses a darker shade of the same hue.
- **FrontDesk StatusBadge:** `rounded-lg px-3 py-1` with icon + text layout.

### Cards / Containers
- **Corner Style:** `rounded-lg` (8px) for summary cards, stat widgets, content panels. Public site uses `rounded-2xl` (16px) on the login form container and `rounded-3xl` (24px) sparingly.
- **Background:** White (`bg-white`) on alt-surface (`bg-slate-50` or `bg-gray-50`). Raised cards get the Surface Raised shadow.
- **Border:** Default cards have no border. When borders are used (sidebar, table rows, modal headers), they are `border-slate-100` or `border-slate-200`.
- **Internal Padding:** Standard `p-4` to `p-6` for cards; login form uses `p-8 md:p-10`.

### Inputs / Fields
- **Style:** Background is `bg-slate-50` (alt-surface), no shadow at rest. Border is 2px `border-slate-50` (matching the bg for a line-free look).
- **Focus:** Background shifts to white, border shifts to indigo-600. No ring (`focus:ring-0`). Transition on border and background.
- **Icons:** Left-positioned icon inside the input (absolute positioning, `pl-5` for the icon, `pl-12` for text). Icon color transitions from `text-slate-400` to `text-indigo-600` on focus.
- **Error:** Red border, red error message below using `InputError` component.
- **Placeholder:** `text-slate-400` — must achieve 4.5:1 contrast against `bg-slate-50` and `bg-white`. The slate-400 against white passes (4.6:1); against slate-50 it drops to ~3.8:1, so inputs use white background at focus but the resting state is acceptable for placeholder.

### Navigation
- **Public Header:** Fixed top bar. Transparent at top of page, transitions to `bg-white/95 backdrop-blur-lg border-b border-slate-100` on scroll. Nav links are 10px uppercase labels with tracking and an underline-on-hover `::after` pseudo-element.
- **Sidebar (Staff/Admin):** 256px fixed sidebar, white background, right border. Nav items are `rounded-lg px-3 py-2` with 14px text, icon + label layout. Active state uses `bg-gray-100`. Bottom section has user avatar + sign out.
- **Footer (Public):** Ink background (`bg-slate-950`), white text. Grid layout with Contact / Location / Legal columns. Subtle indigo glow accent in the background (`bg-indigo-500/5 blur-[120px]`).

### Modals
- **Backdrop:** Fixed inset, `bg-gray-500 bg-opacity-75`, z-50. Click-to-close.
- **Container:** White, `rounded-lg`, shadow-xl. Width constrained via `max-w-sm` through `max-w-2xl`. Header has border-bottom with title + close button. Body is scrollable with `overflow-y-auto`.
- **Transition:** `opacity 0.3s ease` fade.

### Signature: WhatsApp Concierge
- Fixed bottom-right (`bottom-8 right-8`, z-100). Circular emerald button with a pulsing glow ring (`animate-ping` + `animate-pulse-slow`). On hover, a tooltip label slides in from the right ("Chat with Concierge") and the button slowly rotates 360 degrees. The glow shadow is emerald-tinted.

## 6. Do's and Don'ts

### Do:
- **Do** use Figtree as the single typeface across all surfaces, varying weight (300-900) for hierarchy.
- **Do** use the label system (10px, weight 900, uppercase, 0.2em tracking) for form labels, nav links, and footer headings consistently.
- **Do** use shadows freely to separate surfaces — cards, dropdowns, modals all get distinct shadow definitions.
- **Do** use indigo-600 as a sparse accent (≤10%) for interactive signals only.
- **Do** match placeholders to `text-slate-400` and verify ≥4.5:1 contrast against their background.
- **Do** use `text-wrap: balance` on h1-h3 and `text-wrap: pretty` on long prose.
- **Do** cap body text line length at 65-75ch.
- **Do** use semantic z-index scale (dropdown → sticky → modal-backdrop → modal → toast → tooltip).

### Don't:
- **Don't** use cream/sand/beige body backgrounds — the warm-neutral AI default is prohibited.
- **Don't** pair `border: 1px solid` with `box-shadow` blur ≥16px on the same element (ghost-card pattern).
- **Don't** use border-radius above 16px on cards or 24px on containers.
- **Don't** use gradient text (`background-clip: text` with gradient) — use a single solid color.
- **Don't** use glassmorphism (blurs and glass cards) as a decorative default.
- **Don't** use the hero-metric template (big number, small label, gradient accent).
- **Don't** use tiny uppercase tracked eyebrows above every section as a default cadence.
- **Don't** use numbered section markers (01 / 02 / 03) unless the sequence carries information.
- **Don't** use side-stripe borders (`border-left` / `border-right` >1px as accent).
- **Don't** use generic gray text on colored backgrounds — use a darker shade of the background's own hue.
- **Don't** nest cards.
- **Don't** use arbitrary z-index values (999, 9999).
- **Don't** create hand-drawn / sketchy SVG illustrations.
- **Don't** use repeating-linear-gradient stripe backgrounds.
