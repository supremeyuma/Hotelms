# Product

## Register

product

## Users

Multi-role system serving four distinct user groups:

- **Guests**: Hotel guests booking rooms, ordering room service via QR codes, checking bills, and managing their stay through the guest portal.
- **Staff**: Front desk, housekeeping, kitchen, bar, laundry, and maintenance teams using real-time dashboards and order queues for daily operations.
- **Managers**: Department supervisors overseeing operations, approving tasks, and reviewing staff performance metrics.
- **Administrators**: System owners managing room inventory, staff accounts, website content, financial reporting, and system-wide configuration.

## Product Purpose

HotelMS is a comprehensive hotel operations platform that automates and integrates every aspect of hotel management — from guest reservations and room bookings to staff task management, room service ordering, and administrative dashboards. It eliminates disjointed workflows by providing a unified system for all hotel operations, backed by multi-gateway payment processing with automated reconciliation.

## Brand Personality

Modern, sophisticated, efficient. The interface conveys professionalism and trust through clean, intentional design. It should feel capable without being cold — confident enough for a general manager, approachable enough for a housekeeping staff member working under pressure.

## Anti-references

- Generic SaaS templates and boilerplate admin panels. No cookie-cutter layouts, stock icon sets, or default form styling.
- Overly decorative hospitality designs (ornate, gold-heavy, pattern-laden). The brand deserves clarity over embellishment.
- AI-generated "ghost card" patterns (1px border + wide blur shadow together), insanely rounded corners (32px+ cards), and gradient text effects.
- Tired conventions like numbered section markers (01 / 02 / 03), tiny uppercase tracked eyebrows above every section, and hero-metric templates.
- The cream/sand/beige body background default that dominates AI-generated designs.

## Design Principles

1. **Confidence through clarity.** Every screen serves a job. Remove visual noise that doesn't support the task at hand. Staff dashboards should be scannable at a glance; guest pages should feel effortless.

2. **Contrast with care.** Body text must be readable (≥4.5:1 contrast). Gray text on tinted backgrounds is the most common readability failure — avoid it. Use darker shades of the background's own hue rather than generic grays.

3. **Purposeful motion.** Animation serves interaction and navigation, not decoration. Use smooth transitions for state changes, scroll-linked reveals for brand pages, and subtle feedback for actions. Respect reduced motion preferences. Lenis for smooth scroll, staggered reveals for lists, but no redundant reflex.

4. **One visual system.** Shared design tokens (color, spacing, type scale) across brand and product surfaces. The public website and admin dashboards should feel like different rooms of the same building, not different buildings. The palette shifts in saturation and use but shares the same base.

5. **Production-grade, not prototype.** Every state matters — loading, empty, error, and edge cases. Double-submission protection on all forms. Semantic z-index scale. Responsive without breakpoints where possible. Buttons show loading state immediately on submit.

## Accessibility & Inclusion

No formal compliance target specified. Follow general best practices: keyboard-navigable interfaces, visible focus indicators, sufficient color contrast, and `prefers-reduced-motion` support for all animations.
