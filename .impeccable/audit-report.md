# Audit Report: HotelMS

Generated 2026-06-21

## Audit Health Score

| # | Dimension | Score | Key Finding |
|---|-----------|-------|-------------|
| 1 | Accessibility | 2/4 | Widespread contrast failures (slate-400 on white at ~3.5:1), zero ARIA on icon buttons, 10+ images missing alt text |
| 2 | Performance | 3/4 | No layout thrashing, some unoptimized animations (AOS, heavy backdrop-blur), missing lazy loading on images |
| 3 | Theming | 1/4 | Two conflicting palettes (gray-* vs slate-*), no design tokens, dark mode on only ~20% of pages |
| 4 | Responsive Design | 3/4 | Grids work well, overflow-x-auto on tables, but small touch targets (24px) and some fixed widths risk overflow |
| 5 | Anti-Patterns | 1/4 | 3+ major AI tells: massive over-rounding (40-48px), border+wide shadow on 40+ elements, 100+ tiny tracked eyebrows |
| **Total** | | **10/20** | **Acceptable (significant work needed)** |

---

## Anti-Patterns Verdict

**Fail.** This codebase has the three strongest AI-generated design signatures in heavy concentration:

1. **`rounded-[2.5rem]`/`rounded-[3rem]` on cards** — ~115 instances. Virtually every card, modal, and section container across Booking (8 pages), Auth (6 pages), Staff, FrontDesk, and Admin uses 40-48px radii. No brand chooses this.
2. **`border` + `shadow-xl`/`shadow-2xl` on the same element** — 40+ instances. The ghost-card pattern is the template default on auth cards, booking forms, admin report headers, and staff dashboards.
3. **`text-[10px] font-black uppercase tracking-[0.2em]`** — 100+ elements. This specific label motif is the most concentrated AI tell in the entire codebase. It appears on section headers, table columns, form labels, status badges, and card labels across every page group.

Secondary tells: 43 glassmorphism instances (5 identical glass-card patterns on admin report pages), one gradient text on the Events hero.

---

## Executive Summary

- **Audit Health Score: 10/20** (Acceptable)
- **Total issues found:** 6 P1, 18 P2, 8 P3
- **Top 5 critical issues:**
  1. [P1] Over-rounded cards (40-48px) on ~115 elements across all page groups — immediate visual tell
  2. [P1] Border + wide shadow on 40+ elements — ghost-card pattern
  3. [P1] Tiny tracked eyebrows on 100+ elements — most concentrated AI tell
  4. [P1] Two conflicting color palettes (gray vs slate) with no design tokens
  5. [P2] Contrast failures: slate-400 on white (~3.5:1) across 20+ pages — WCAG AA violation

---

## Detailed Findings by Severity

### P1 — Major (fix before next release)

**1. [P1] Over-rounded cards/sections**
- **Location**: Virtually all Pages/Booking/*.vue, Pages/Auth/*.vue, Pages/Staff/*.vue, Pages/FrontDesk/*.vue, Pages/Admin/*.vue, Pages/Public/*.vue
- **Category**: Anti-Pattern
- **Impact**: 40-48px border-radius on cards is the strongest AI visual tell in this codebase. It makes the UI feel like a generic template, not a serious hotel management system.
- **Recommendation**: Reduce all card/container `rounded-[2.5rem]` → `rounded-xl` (12px) and `rounded-[3rem]` → `rounded-2xl` (16px). Keep larger radii only on hero/display sections.
- **Suggested command**: `$impeccable polish --fix-rounding`

**2. [P1] Border + wide shadow on same element**
- **Location**: Auth pages (6), Booking pages (8), Admin reports (5), Staff dashboards, FrontDesk cards — 40+ instances
- **Category**: Anti-Pattern
- **Impact**: The ghost-card pattern (1px border + shadow-xl/2xl) is the second-strongest AI tell. It adds visual noise without serving a functional purpose.
- **Recommendation**: For each element, pick either a 1px border OR a shadow (≤8px blur), never both.
- **Suggested command**: `$impeccable distill --remove-ghost-cards`

**3. [P1] Tiny tracked eyebrows on 100+ elements**
- **Location**: Every page group: SummaryCard.vue, Admin dashboard headers, Booking labels, Staff status badges, FrontDesk cards, Public section labels
- **Category**: Anti-Pattern
- **Impact**: `text-[10px] font-black uppercase tracking-[0.2em]` on every section label is the single most concentrated AI signature. It reads as a template default, not a brand voice.
- **Recommendation**: Reduce to `text-xs uppercase font-semibold tracking-wide` on most labels. Reserve the 10px black uppercase only for truly structural labels (footer headings, form labels).
- **Suggested command**: `$impeccable typeset --fix-eyebrows`

**4. [P1] Two conflicting color palettes**
- **Location**: System-wide. `gray-*`/`indigo-*` in Admin/Reports/Welcome; `slate-*`/`indigo-*`/`emerald-*` in Booking/Public/Staff/Guest/FrontDesk
- **Category**: Theming
- **Impact**: Pages look like they belong to different applications. The Admin section uses gray tones; the Booking flow uses slate tones. Brand inconsistency undermines trust.
- **Recommendation**: Consolidate to one palette. Slate is the dominant system (used across 60% of pages). Migrate Admin/Reports from `gray-*` to the `slate-*` palette to match DESIGN.md.
- **Suggested command**: `$impeccable colorize --unify-palette`

**5. [P1] Missing dark mode on 70+ files**
- **Location**: All Booking/Public/Staff/Guest/FrontDesk/Auth/Profile pages, all layouts (13), all components except KpiCard
- **Category**: Theming
- **Impact**: Dark mode only works on Admin/Reports pages. Toggling dark mode on other pages causes invisible text and broken layouts. This is a WCAG failure for users who require dark mode.
- **Recommendation**: Add `dark:` variants systematically. Start with layouts, then component library, then page groups.
- **Suggested command**: `$impeccable harden --dark-mode`

**6. [P1] No design tokens or CSS custom properties**
- **Location**: System-wide. All colors use hard-coded Tailwind class names
- **Category**: Theming
- **Impact**: No single source of truth for brand colors. Changing the brand accent requires editing every .vue file. The DESIGN.md token specification has no binding mechanism.
- **Recommendation**: Extract CSS custom properties from DESIGN.md token spec, define in `app.css`, migrate Tailwind config to reference them.
- **Suggested command**: `$impeccable extract --tokens`

---

### P2 — Minor (fix in next pass)

**7. [P2] Contrast: slate-400 on white fails WCAG AA**
- **Location**: 20+ files across Staff/Cleaning, FrontDesk/Rooms, Booking/, Guest/, Components/ (SummaryCard, KpiCard, Stat, NavLink)
- **Category**: Accessibility
- **Impact**: `slate-400` (#94a3b8) on white (#ffffff) scores ~3.5:1, below the 4.5:1 AA threshold. This affects secondary labels, helper text, and icon colors that users with low vision cannot read.
- **Recommendation**: Change secondary text from `slate-400` to `slate-500` or `slate-600` where contrast needs to pass AA.
- **Suggested command**: `$impeccable clarifiy --contrast`

**8. [P2] Zero ARIA labels on icon-only buttons**
- **Location**: Modal close buttons (Modal.vue, CleaningModal.vue), icon-only actions (FrontDesk/Rooms, Billing, Laundry), search icons
- **Category**: Accessibility
- **Impact**: Screen reader users get no indication of what these buttons do. Close buttons read as "button" with no label.
- **Recommendation**: Add `aria-label` to all icon-only interactive elements.
- **Suggested command**: `$impeccable harden --aria-labels`

**9. [P2] Images missing alt text**
- **Location**: RoomDetail.vue, Events.vue (x2), Guest/LaundryModal.vue, Admin/WebsiteContent/Index.vue, Staff/Laundry/Show.vue, FrontDesk/LaundryRequests.vue, Components/ImageUploader.vue (x2), CreateLaundryOrderModal.vue
- **Category**: Accessibility
- **Impact**: 10+ images invisible to screen readers. Room images, event images, and uploaded images have no text alternative.
- **Recommendation**: Add `alt` attributes. For user-uploaded images, add a descriptive caption field and use that as alt text.
- **Suggested command**: `$impeccable harden --alt-text`

**10. [P2] Small touch targets (24px)**
- **Location**: Guest/LaundryModal.vue, Guest/Menu.vue (quantity buttons at 16px), Staff/Cleaning/CleaningModal.vue, FrontDesk (back arrow, icons), Public/Gallery navigation, Admin/Events remove buttons
- **Category**: Responsive
- **Impact**: WCAG 2.5.5 requires 44x44px minimum for touch targets. 24px buttons and p-1 quantity controls are unusable on mobile for users with motor impairments.
- **Recommendation**: Increase icon buttons to min 44x44px hit area (use padding + larger clickable area with `w-11 h-11`).
- **Suggested command**: `$impeccable adapt --touch-targets`

**11. [P2] `<div>` used as interactive button**
- **Location**: Components/Dropdown.vue (trigger), Components/Modal.vue (backdrop), Booking/ImageLightbox.vue (backdrop), Staff/CleaningModal (backdrop)
- **Category**: Accessibility
- **Impact**: Non-semantic clickable elements are invisible to screen readers and inoperable via keyboard. The Dropdown trigger has no `role="button"`, `tabindex`, or Enter/Space handler.
- **Recommendation**: Add `role="button"`, `tabindex="0"`, and `@keydown.enter`/`@keydown.space` handlers to clickable divs.
- **Suggested command**: `$impeccable harden --keyboard`

**12. [P2] Missing focus styles on some inputs**
- **Location**: Pages/Guest/RoomDashboard.vue (select, textarea, date input), Pages/Public/Booking.vue (form inputs)
- **Category**: Accessibility
- **Impact**: Keyboard users cannot see which field is focused.
- **Recommendation**: Add `focus:` styling (ring or border color change) to all interactive elements.
- **Suggested command**: `$impeccable harden --focus-indicators`

**13. [P2] Glassmorphism on 43 elements**
- **Location**: Admin report pages (5 identical patterns), Public hero sections, Booking overlays, Modal backdrops
- **Category**: Anti-Pattern
- **Impact**: Decorative blur effects add visual noise and are an AI signature pattern. The 5 admin report panels share an identical glass-card template.
- **Recommendation**: Remove decorative glass. Use solid background colors with tonal layering. Keep glass only for modal backdrops and the fixed navbar.
- **Suggested command**: `$impeccable quieter --glassmorphism`

**14. [P2] Fixed-width values risk overflow**
- **Location**: ClubPos/Dashboard.vue (`w-[380px]`), 5 files with `min-w-[220-280px]`, FrontDesk/FilterSearch.vue (`min-w-[280px]`)
- **Category**: Responsive
- **Impact**: On viewports <400px, `w-[380px]` will overflow. Minimum widths may cause horizontal scroll on smaller phones.
- **Recommendation**: Replace fixed pixel widths with responsive alternatives (`min-w-0`, `w-full`, or percentage-based layouts).
- **Suggested command**: `$impeccable adapt --overflow`

---

### P3 — Polish (fix if time permits)

**15. [P3] Gradient text on Events page**
- **Location**: Pages/Public/Events.vue:29
- **Category**: Anti-Pattern
- **Impact**: Minor visual tell. One instance only.
- **Recommendation**: Replace with solid indigo-600 color.
- **Suggested command**: `$impeccable quieter --gradient-text`

**16. [P3] Missing `for`/`id` label associations on some forms**
- **Location**: Staff/Menu/Index.vue (search input), Public/Booking.vue (inputs), Staff/QuickAction.vue, Staff/Laundry/LaundryItems.vue
- **Category**: Accessibility
- **Impact**: Currently using implicit label wrapping which is valid but less robust. Screen readers may lose association in some contexts.
- **Recommendation**: Add explicit `for`/`id` pairs on all form inputs.
- **Suggested command**: `$impeccable harden --form-labels`

**17. [P3] AOS and Lenis animation cost**
- **Location**: Pages/Public/Home.vue
- **Category**: Performance
- **Impact**: AOS and Lenis are both loaded on the public home page. Both are animation frameworks that add ~30KB+ to the bundle. AOS is known for frame drops on low-end devices.
- **Recommendation**: Evaluate if Lenis smooth scroll is worth the cost. Consider removing AOS in favor of native CSS scroll-timeline animations.
- **Suggested command**: `$impeccable optimize --animations`

**18. [P3] Welcome.vue uses `zinc-*` instead of `gray-*`/`slate-*`**
- **Location**: Pages/Welcome.vue
- **Category**: Theming
- **Impact**: Welcome page has its own palette (zinc) for dark mode, inconsistent with the rest of the app.
- **Recommendation**: Move to the primary palette.
- **Suggested command**: `$impeccable colorize --welcome-page`

---

## Patterns & Systemic Issues

1. **No design token system.** No CSS custom properties defined anywhere. Colors are hard-coded Tailwind classes, making palette-wide changes impossible without touching every component.
2. **Two competing palettes.** The `gray-*` palette (Admin/Reports) and `slate-*` palette (everything else) create visual fragmentation. About 30% of Admin pages also mix in `gray-*` tokens from Laravel Breeze defaults.
3. **Identical anti-pattern blocks.** Auth (6 pages) and Booking (8 pages) share identical card templates — same `rounded-[2.5rem]`, same `shadow-xl shadow-slate-200/60 border`. Admin reports (5 pages) share identical glass cards.
4. **Dark mode abandoned.** Only Admin and Reports were built with dark mode. The other 60+ pages, all layouts, and all components have none — suggesting dark mode was started but never completed.
5. **Touch targets consistently too small.** The same `w-6 h-6` (24px) icon button pattern repeats across Guest/Menu, Staff/Laundry, Staff/Cleaning, FrontDesk, and Admin.

---

## Positive Findings

- **Responsive grids are excellent.** `grid-cols-1 md:grid-cols-2 xl:grid-cols-N` pattern is consistent and correctly implemented across all page groups. No desktop-only layouts found.
- **Overflow handling is good.** `overflow-x-auto` on 33 table wrappers covers all the data tables. No cut-off table content.
- **Custom cursor and magnetic buttons on the public site** show intentional effort at interaction design.
- **WhatsApp concierge component** is a distinctive brand element with thoughtful motion (pulse ring, hover tooltip, rotation).
- **RoomSelection.vue** has a properly keyboard-accessible interactive element (`role="button" tabindex="0" @keydown`).
- **Semantic z-index values.** No 999/9999 found anywhere. Highest is `z-[100]` for modals, which is reasonable.
- **No cream/sand/beige backgrounds.** The codebase avoids the most common AI background tell entirely.
- **No side-stripe border abuse.** All 8 instances are contextually appropriate (active nav, timelines, alerts).

---

## Recommended Actions

1. **[P1] `$impeccable distill --fix-rounding`**: Reduce all card/section `rounded-[2.5rem]` and `rounded-[3rem]` to `rounded-xl`/`rounded-2xl`
2. **[P1] `$impeccable distill --remove-ghost-cards`**: Remove `shadow-xl`/`shadow-2xl` from bordered elements or remove borders
3. **[P1] `$impeccable typeset --fix-eyebrows`**: Replace `text-[10px] font-black uppercase tracking-[0.2em]` with restrained label styling
4. **[P1] `$impeccable colorize --unify-palette`**: Migrate Admin/Reports from `gray-*` to `slate-*`
5. **[P2] `$impeccable clarifiy --contrast`**: Fix slate-400 contrast failures across 20+ files
6. **[P2] `$impeccable harden --aria-labels`**: Add aria-label to all icon-only buttons
7. **[P2] `$impeccable adapt --touch-targets`**: Increase icon buttons to 44x44px minimum
8. **[P3] `$impeccable polish`**: Final quality pass after all fixes

You can ask me to run these one at a time, all at once, or in any order you prefer.

Re-run `$impeccable audit` after fixes to see your score improve.
