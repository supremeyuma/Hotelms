# HotelMS — Full Frontend UI/UX Audit

**Scope:** 144 pages + 58 components + 7 layouts across 8 modules (Public, Auth, Booking, FrontDesk, Staff Ops, Admin, Guest/RoomService, Reports, ClubPOS). Every claim below is code-verified (file:line).

## Anti-pattern verdict

**Not "AI slop"** — the slate/ink + indigo system is intentional and genuinely good on staff/guest surfaces. The failure is **drift and inconsistency**: no design tokens, four hand-copied hero/metric/eyebrow templates, raw `slate` vs `gray` mixing, and 32px radii creeping in where 16px was the rule. It's inconsistent-without-purpose rather than generic.

## Audit Health Score

| # | Dimension | Score | Key Finding |
|---|-----------|-------|-------------|
| 1 | Accessibility | 2 | Widespread <4.5:1 text; no modal dialog semantics anywhere |
| 2 | Performance | 2 | 12 dead routes, missing import crashes, Echo leaks, eager images |
| 3 | Responsive | 2 | <44px touch targets across ops + POS + guest; no admin mobile nav |
| 4 | Theming | 1 | Zero tokens; gray/slate mixing; invalid palette classes silently no-op |
| 5 | Anti-Patterns | 2 | Side-stripes, gradient text, hero-metric, radius>16px, glassmorphism |
| **Total** | | **9/20** | **Poor — major overhaul needed in fixable, systemic ways** |

---

## P0 — Blocking (breaks task / crashes page)

1. **[Crash] `Staff/QuickAction.vue:31`** — `usePage()` called but **not imported** (only `ref`, `router`). Live route `staff.quick-action.index` (`StaffActionController:30`) → `ReferenceError` on load; page cannot render. Fix: add `usePage` to the `@inertiajs/vue3` import.

2. **[Crash] `RoomService/RoomService.vue:34`** — `usePage()` not imported; `Tabs`/`OrderForm` referenced in template but commented out → `undefined` component + crash. Live route `RoomServiceController:40`.

3. **[12 dead routes] Controllers render pages that don't exist** (verified: dirs absent):
   - `Orders/KitchenQueue`, `Orders/LaundryQueue`, `Orders/HousekeepingQueue`, `Orders/MaintenanceQueue`
   - `Bookings/View`, `Staff/OrdersQueue`, `Staff/Profile`, `Public/CheckIn`, `RoomService/Track`
   - `Admin/Receipts/Index`, `Admin/Receipts/Show`, `FrontDesk/LaundryRequestDetail`
   - Each throws "page not found" at runtime. Fix: build or delete the routes.

4. **[Crash] `Public/BookingConfirmation.vue`** (routed via `BookingController:685`) — uses legacy `<inertia-link>` and `<PublicLayout>` with no imports; only `usePage` imported. Fix: migrate to Inertia `Link` + import layout, or re-point route to the working `Booking/Confirmation.vue`.

5. **[Correctness] `Admin/WebsiteContent/Index.vue:86`** — `imageForm.post(url), {onSuccess...}` comma-operator bug: options never passed, `isUploading` hangs forever, errors silent. Move options inside `post(...)`.

6. **[Wrong module] `Staff/Bar/OrderHistory.vue:3`** — imports `KitchenLayout` (bar staff see Kitchen sidebar + copy "cancelled kitchen orders"). Use `BarLayout`.

7. **[Wrong module] `Guest/OrderHistory.vue:3`** — guest page rendered inside staff `KitchenLayout` with staff copy + `v-html` pagination. Use `GuestLayout`, render labels as text.

---

## P1 — Major (WCAG AA fail / significant usability / money risk)

8. **[Accessibility, all modules] Low-contrast text** — `text-slate-400` on white ≈ 2.6:1, `slate-500` ≈ 4.1:1, even `slate-300/400` (1.5–2.4:1). Pervasive on every module: RoomDashboard, OrderStatus, Menu, Kitchen/Bar/Laundry, FrontDesk, Reports, Home, Booking. **Fix:** promote informational text to `slate-600/700`; reserve ≤`slate-500` for decorative.

9. **[A11y, shared] `Modal.vue` is not a dialog** — no `role="dialog"`/`aria-modal`/`aria-labelledby`, **no focus trap, no focus restore**, close is bare `&times;`. This single component is used by FrontDesk, Guest, Laundry, Auth, Admin — fixing it fixes ~20 surfaces. Same gap in hand-rolled modals: CleaningModal, LaundryItems, Laundry/Show, Menu/Index, Guest/Menu, ClubPOS PaymentModal + StaffPinModal.

10. **[A11y] Clickable `<div @click>` cards with no keyboard** — Cleaning board, Kitchen/Bar order queues, Laundry, Guest/Menu, Gallery lightbox, Reports SummaryCard are mouse-only and screen-reader-dead. Fix: `<button>` or `role="button" tabindex="0"` + Enter.

11. **[Money] `FrontDesk/Rooms/Billing.vue`** — `submitPayment()` no `form.processing` guard, button un-disabled → duplicate charges. Contrast: Bookings Create/Edit, Billing/Show, CheckInModal all do it right. Also `Admin/WebsiteContent/Gallery.vue:278` upload, extend-stay on RoomDashboard, and cleaning/laundry/status buttons lack loading states.

12. **[A11y] Zero modal a11y / no labels** — grep confirms **zero** `role="dialog"`, `aria-label` on close buttons, and pervasive unassociated labels (no `for`/`id`) across Auth, FrontDesk, Admin, Reports. `TextInput.vue` supports **no** `id`/`label` prop → App-wide fix.

13. **[Perf] Echo listener leak** — `Kitchen/Orders.vue:22`, `Bar/Orders.vue:22` subscribe `.channel().listen()` with **no `leaveChannel` on unmount** → duplicate subscriptions across Inertia navigations (Laundry Dashboard does it correctly — copy it).

14. **[Functionality] `Staff/Menu/Index.vue`** — `filteredCategories` computed never used (search box + "Show unavailable" do nothing); `<draggable v-model="props.categories">` mutates a prop.

15. **[UX] Dead controls** — Kitchen/Bar Dashboards `Settings2` FAB (no handler, `md:hidden`), FrontDesk GuestRequests "Filter Feed" (no handler), MenuOnline item div `cursor-pointer` with no click.

16. **[Perf] `Admin/Rooms/Index.vue:34`** — `watch(filters)` fires `router.get` on every keystroke (no debounce); eager `<img>` across rooms/roomtypes/public.

17. **[A11y] Focus stripped** — `outline:none !important` on all 5 auth pages + `focus:ring-0` + no visible ring on Staff/CheckIn, Events/CheckIn, BaseStaffLayout mobile controls. Replace with `focus-visible:ring-4 ring-indigo-500/30`.

18. **[Correctness] Non-existent config classes silently no-op** — `bg-slate-850`, `bg-slate-750`, `hover:bg-slate-750`, `scrollbar-none` don't exist in the default palette (ClubPOS Dashboard, OpenDocketsBar, ProductCard, CategoryTabs) — the right dock bg, hovers, and scrollbar-hiding never render.

19. **[UX] `Admin/AuditLogs/Index.vue`** — `<pagination>` not imported (no pagination controls), legacy gray `dark:` theme matches nothing.

20. **[A11y] ClubPOS `StaffPinModal`** — wrong-PIN deadlocks the keypad (`loading` never reset — parent only `alert()`s); PIN overlay not a dialog; blank PIN placeholder renders a focusable unlabeled button.

---

## P2 — Minor (annoyance / polish)

21. **[A11y] Icon-only controls unlabeled** — Back/Close/trash/Eye edit across FrontDesk, Admin, Guest; hover-only reveal (`opacity-0 group-hover`) makes destructive actions unreachable on touch.

22. **[A11y] ClubPOS color-only status** — `ProductCard` low-stock is a 10px amber dot (color-blind → invisible); `OpenDocketsBar` slate-500 on slate-700 ≈ 2.2:1; remove/void hidden behind hover on a touch device.

23. **[A11y] Heading skips** — Staff Dashboard & QuickAction start at `h2` (no `h1`); ClubPOS has no `h1`; Reports tabs skip h2→h3.

24. **[Responsive] Touch targets <44px** — order status buttons (`text-[9px]`), quantity ± (`p-1` ≈24px), pagination `px-3 py-1` ≈36px, sidebar items `px-3 py-2`, POS 40px. Add `min-h-[44px]`.

25. **[Anti-pattern] Radius drift & ghost-cards** — `rounded-[2rem]`/`rounded-3xl` (24–32px) on cards across FrontDesk Bookings, Guest, Reports TrendChart, Auth login; 1px border + blur shadow default. Cap at 16px via token.

26. **[Anti-pattern] Copy-pasted templates, zero tokens** — the `bg-slate-900` hero + metric-card block is duplicated in Admin Dashboard, Bookings Index, DiscountCodes, Reports Revenue/Occupancy; the dark `bg-[linear-gradient(145deg,#0f172a,#1e293b)]` panel in 4+ places. Extract a `ReportHero`/`MetricCard` component + define tokens.

27. **[Anti-pattern] Side-stripes + gradients** — `border-l-4` (Reports MetricCard, DepartmentStatusCard), gradient card fills; gradient text in `Events.vue:29` `MEMORIES`; glassmorphism (`backdrop-blur`) accents on Home/Events/EventDetail/TrendChart.

28. **[Theming] `gray` vs `slate` mixing** — Menu.vue/GuestLayout/StaffSidebar/AdminSidebar use `gray-*`; every other surface uses `slate-*`. Standardize on one neutral scale.

29. **[Perf] Chart.js** — `Chart.vue` never calls `destroy()` on unmount (leak); `TrendChart` + `ChartComponent` pull full `chart.js/auto` (~200KB) eagerly. Lazy-load.

30. **[A11y] Pagination** — `Pagination.vue` uses `v-html="link.label"` (XSS surface) + color-only active + no `aria-current`. Also `OrderHistory.vue` pagination injects raw link HTML.

31. **[Cleanup] Dead code shipped** — `console.log` across Home, Payment, ClubLounge, RoomDetail, Staff Dashboard, Events (Index/Show/Create/Edit), WebsiteContent **renders a `<pre>` debug overlay to all users**; `Staff/CheckIn.vue` is dead (never routed, broken `await router.post`).

32. **[Scope] Legacy unrouted pages** — `Public/Booking.vue`, `RoomTypes.vue`, `RoomDetail.vue`, `Gallery2.vue`, `Contact.vue` are dead code (not referenced by controllers) but reference broken imports. Delete or restore.

---

## Systemic issues (root causes, highest leverage)

1. **No design token system** (`tailwind.config.js` extends only `fontFamily`). Fixing this addresses theming drift, radius, ghost-card, all at once.
2. **Two shared components carry ~30% of the a11y debt:** `Modal.vue` (no dialog semantics/focus trap) and `TextInput.vue` (no id/label). Upgrading both fixes a11y across every module.
3. **Duplicate page patterns** (hero metric card, order queue cards, modals, pagination) should be extracted to shared components — the same code is copy-pasted 4+ times per pattern.
4. **No lint/CI gate for** dead routes, missing imports, `console.log`, or invalid Tailwind classes — a static check would have caught all P0s.

## Positive findings (keep)

- **Double-submission protection is the norm** — `:disabled="form.processing"` correctly used across Bookings, Billing, Auth, Feedback, Admin forms, Threads, and (excellently) ClubPOS PaymentModal (can't pay while underpaying) + Stock.
- **`RoomScheduling/Index.vue` is the model page** — wrapping labels, error under every field, accessible radio cards, full processing states.
- **`FeedbackForm.vue` / Guest feedback** — real labels, loading states, clear non-technical guest copy.
- **Laundry/Print.vue** — genuine working print stylesheet (`@media print`, `@page`, color-adjust). Rare.
- **Reduced-motion handled responsibly** — FrontDesk feed + POS hover effects are `motion-safe:`/`prefers-reduced-motion` gated.
- **Clickable summary cards already link to filtered records** where implemented (Staff, Bookings, Inventory, Feedback).

## Recommended actions (priority order)

1. **[P0] Fix the 5 crashes + 12 dead routes** — harden QuickAction, RoomService, BookingConfirmation; then delete/create missing pages
2. **[P0] Modal + TextInput dialog/label semantics** — harden `Components/Modal.vue`, `Components/TextInput.vue`
3. **[P1] Design tokens + template extraction** — colorize, then extract
4. **[P1] Contrast sweep** — promote to slate-600 then re-audit
5. **[P1] Double-submission on Billing/cleaning/laundry** — harden
6. **[P1] Echo cleanup + modals on Staff Ops** — audit / animate
7. **[P2] Radius + anti-pattern pass** — quieter
8. **[P2] Guest + POS touch targets** — adapt
9. Finish with polish
