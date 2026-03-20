# 🎨 Menu System - Visual Design Guide

## Three Menu Pages Design Overview

### 1️⃣ Room Service Menu (`/guest/room/{token}/menu/{type}`)

```
┌─────────────────────────────────────────────────────────────────┐
│ ← Back           Room Service Menu              Orders  →        │
└─────────────────────────────────────────────────────────────────┘

┌─ CATEGORIES ────────────────────────────────────────────────────┐
│ [KITCHEN] [BAR] [DESSERTS]                                       │
└─────────────────────────────────────────────────────────────────┘

┌──────────────────────────────┐  ┌──── YOUR CART [STICKY] ──┐
│ ITEMS GRID (2-3-4 cols)      │  │ Total: ₦15,500           │
│                              │  │                           │
│ ┌────┐ ┌────┐ ┌────┐        │  │ Items:                   │
│ │ 🍕 │ │ 🍔 │ │ 🍜 │        │  │ ┌─────────────────────┐ │
│ │Fish │ │Rice │ │Soup │     │  │ │Grilled Fish x2      │ │
│ │₦2500│ │₦1800│ │₦900│      │  │ │₦5,000               │ │
│ │  +-│ │  +-│ │  +-│        │  │ │     [Remove]         │ │
│ └────┘ └────┘ └────┘        │  │ └─────────────────────┘ │
│                              │  │ ┌─────────────────────┐ │
│ ┌────┐ ┌────┐ ┌────┐        │  │ │Fried Rice x1        │ │
│ │ 🍗 │ │ 🥗 │ │ 🍨 │        │  │ │₦1,800               │ │
│ │Chix │ │Salad│ │Desert      │  │ │     [Remove]         │ │
│ │₦3k  │ │₦1.5│ │₦800│       │  │ └─────────────────────┘ │
│ │  +-│ │  +-│ │  +-│        │  │                           │
│ └────┘ └────┘ └────┘        │  │ ────────────────────────  │
│                              │  │ Subtotal  ₦15,300        │
│                              │  │ ────────────────────────  │
│                              │  │ TOTAL     ₦15,500        │
│                              │  │                           │
│                              │  │ [REVIEW ORDER]           │
│                              │  │ [Add items to start]     │
│                              │  │                           │
└──────────────────────────────┘  └──────────────────────────┘

┌─ PAYMENT MODAL (On Review) ─────────────────────────────────────┐
│                    Confirm Order                               │
│                                                                 │
│ Items:                                          Total: ₦15,500 │
│ ✓ Grilled Fish (2) - ₦5,000                                   │
│ ✓ Fried Rice (1) - ₦1,800                                     │
│ ✓ Salad (2) - ₦3,000                                          │
│                                                                 │
│ Payment Method:                                                │
│ ⬤ Pay Now          ○ Pay on Delivery                           │
│                                                                 │
│ [Cancel] [Confirm]                                             │
└─────────────────────────────────────────────────────────────────┘

DESIGN DETAILS:
✅ Header: Sticky, gray-100 background
✅ Categories: Dark buttons, rounded-full
✅ Items: White cards, hover scale 1.05
✅ Cart: Sticky sidebar, shadow-lg
✅ Modal: Dark overlay, centered box
✅ Colors: Black primary, gray-100 secondary
✅ Spacing: 4px-8px padding, generous gaps
```

---

### 2️⃣ Public Online Menu (`/menu/online/{type}`)

```
┌─────────────────────────────────────────────────────────────────┐
│ ← Back Home       Order Online              [Heading]           │
│                  Kitchen Menu                                    │
└─────────────────────────────────────────────────────────────────┘

┌─ CATEGORIES ────────────────────────────────────────────────────┐
│ [KITCHEN] [BAR] [BEVERAGES] [DESSERTS]                          │
└─────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────┐  ┌──── YOUR CART [STICKY] ──┐
│ ITEMS GRID (2-3-4 cols)          │  │                           │
│                                  │  │ 🛒 Your Cart             │
│ ┌──────────────┐ ┌─────────────┐ │  │                           │
│ │     🍕       │ │     🍔      │ │  │ No items yet              │
│ │   [Pizza]    │ │  [Burger]   │ │  │ [Shopping cart icon]       │
│ │  Description │ │Description  │ │  │                           │
│ │   ₦2,500     │ │   ₦1,800    │ │  │ ────────────────────────  │
│ │  -[+] [+] [+]│ │-[+] [+] [+]│ │  │ Subtotal:  ₦0             │
│ └──────────────┘ └─────────────┘ │  │ ────────────────────────  │
│                                  │  │ TOTAL:     ₦0             │
│ ┌──────────────┐ ┌─────────────┐ │  │                           │
│ │     🍜       │ │     🍗      │ │  │ Add items to start         │
│ │   [Noodles]  │ │  [Chicken]  │ │  │                           │
│ │  Description │ │Description  │ │  │                           │
│ │   ₦1,200     │ │   ₦3,000    │ │  │                           │
│ │-[+] [+] [+]│ │-[+] [+] [+]│ │  │ [Proceed to Payment]      │
│ └──────────────┘ └─────────────┘ │  │                           │
│                                  │  │                           │
└──────────────────────────────────┘  └──────────────────────────┘

┌─ PAYMENT MODAL (On Confirm) ────────────────────────────────────┐
│                    Confirm Order                               │
│                                                                 │
│ Items:                                          Total: ₦8,500  │
│ ✓ Pizza (1) - ₦2,500                                           │
│ ✓ Burger (2) - ₦3,600                                          │
│ ✓ Noodles (1) - ₦1,200                                         │
│ ✓ Fries (1) - ₦1,200                                           │
│                                                                 │
│ 🔵 Payment Method:                                              │
│    Online Payment (Prepaid) - ONLY OPTION                      │
│    [Flutterwave / Paystack will be used]                       │
│                                                                 │
│ [Cancel] [Pay Now ₦8,500]                                      │
└─────────────────────────────────────────────────────────────────┘

DESIGN DETAILS:
✅ Header: Navigation layout with description
✅ Categories: pill-shaped buttons, smooth transitions
✅ Items: Hover shadow effect, scale animation
✅ Images: 3:2 aspect ratio, lazy loaded
✅ Cart: Prominent sidebar position
✅ Total: Large, bold font
✅ Colors: Black, white, green accents
✅ Layout: Premium, spacious design
```

---

### 3️⃣ Public View-Only Menu (`/menu/view/{type}`)

```
┌─────────────────────────────────────────────────────────────────┐
│ ← Back          Kitchen Menu            (empty space)          │
│                 Browse available items                          │
└─────────────────────────────────────────────────────────────────┘

┌─ CATEGORIES ────────────────────────────────────────────────────┐
│ [KITCHEN] [BAR]                                                 │
└─────────────────────────────────────────────────────────────────┘

┌─ COLLECTIONS ───────────────────────────────────────────────────┐
│ [All Items] [Appetizers] [Mains] [Sides] [Desserts]            │
└─────────────────────────────────────────────────────────────────┘

┌─ ITEMS GRID (2-3-4 cols) ──────────────────────────────────────┐
│                                                                 │
│ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐            │
│ │   [Image]    │ │   [Image]    │ │   [Image]    │            │
│ │   Grilled    │ │   Fried      │ │   Caesar     │            │
│ │    Fish      │ │    Rice      │ │    Salad     │            │
│ │Delicious...  │ │Flavored...   │ │Fresh & crisp │            │
│ │   ₦2,500     │ │   ₦1,800     │ │   ₦1,500     │            │
│ │ ⭐ Popular   │ │ ⭐ Popular   │ │ ⭐ Popular   │            │
│ └──────────────┘ └──────────────┘ └──────────────┘            │
│                                                                 │
│ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐            │
│ │   [Image]    │ │   [Image]    │ │   [Image]    │            │
│ │   Chicken    │ │   Chocolate  │ │    Fruit     │            │
│ │   Steak      │ │    Cake      │ │    Juice     │            │
│ │Grilled & ...  │ │Creamy & rich │ │Fresh & cold  │            │
│ │   ₦3,000     │ │   ₦2,000     │ │   ₦800      │            │
│ │ ⭐ Popular   │ │ ⭐ Popular   │ │ ⭐ Popular   │            │
│ └──────────────┘ └──────────────┘ └──────────────┘            │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘

┌─ ITEM DETAIL MODAL (On Item Click) ─────────────────────────────┐
│         [Image Full Size]                                       │
│                                                                 │
│ Grilled Fish Steak                                              │
│                                                                 │
│ Delicious grilled fish steak prepared with fresh herbs,        │
│ served with seasonal vegetables and lemon butter sauce.         │
│ A signature dish perfect for seafood lovers.                    │
│                                                                 │
│ ────────────────────────────────────────────────────────────── │
│ Price:       ₦2,500                                             │
│ Prep Time:   25 minutes                                         │
│ ────────────────────────────────────────────────────────────── │
│                                                                 │
│ [Close] [Place Order]                                           │
│         ← Directs to online menu                               │
└─────────────────────────────────────────────────────────────────┘

DESIGN DETAILS:
✅ Minimal navigation
✅ Elegant item cards with hover effects
✅ Image-first design (prominent visuals)
✅ Description text preview
✅ Star ratings for popular items
✅ Modal for full details
✅ Soft, premium color scheme
✅ Touch-friendly spacing
✅ Call-to-action button links to online menu
```

---

## 🎨 Design System Components

### Color Palette
```
Primary:    #000000 (Pure Black)
Secondary:  #FFFFFF (Pure White)
Neutral:    #F5F5F5 (Background)
           #E5E5E5 (Borders/Dividers)
           #9CA3AF (Text Secondary)
Accent:     #10B981 (Green - only room menu)
           #3B82F6 (Blue - for info)
```

### Typography
```
Header (H1):     Font-size: 24px, Weight: 900 (black)
Title (H2):      Font-size: 20px, Weight: 700 (bold)
Subheading (H3): Font-size: 16px, Weight: 600 (semibold)
Body:            Font-size: 14px, Weight: 400 (regular)
Small:           Font-size: 12px, Weight: 500 (medium)
```

### Spacing Scale
```
xs: 4px   (inline elements)
sm: 8px   (components)
md: 16px  (sections)
lg: 24px  (layout)
xl: 32px  (major sections)
```

### Border Radius
```
Small:     4px    (inputs, buttons)
Medium:    8px    (cards, modals)
Large:    12px    (rounded pills)
Full:    9999px   (buttons, avatars)
```

### Shadows
```
sm: 0 1px 2px rgba(0,0,0,0.05)
md: 0 4px 6px rgba(0,0,0,0.1)
lg: 0 10px 15px rgba(0,0,0,0.1)
xl: 0 20px 25px rgba(0,0,0,0.1)
```

### Transitions
```
Default: 0.3s ease
Fast:    0.15s ease
Slow:    0.5s ease
```

---

## 📱 Responsive Breakpoints

```
Mobile:       < 640px   (2 columns, full width)
Tablet:       640-1024px (3 columns)
Desktop:      > 1024px   (4 columns)
Large:        > 1280px   (same as desktop)
```

---

## 🎬 Animations & Interactions

### Hover Effects
```
Item Cards:
- Scale: 1 → 1.02
- Shadow: sm → md
- Duration: 300ms

Buttons:
- Opacity: 1 → 0.9
- Duration: 150ms

Category Tabs:
- Background: light → dark
- Color: gray → white
- Duration: 200ms
```

### Transitions
```
Cart Sidebar:
- Fade + slide in
- 400ms ease

Modals:
- Backdrop fade in
- Content slide up
- 300ms ease

Toasts:
- Fade + translate up
- 400ms ease
- Auto-dismiss after 3.5s
```

---

## 🔍 Visual Hierarchy

### Room Menu
```
Level 1: Header (Menu Type)
Level 2: Categories (Large buttons)
Level 3: Items (Medium cards)
Level 4: Details (Small text)
Level 5: Cart (Floating sidebar)
```

### Online Menu
```
Level 1: Page Title + Description
Level 2: Categories
Level 3: Items Grid (High prominence)
Level 4: Cart Sidebar
Level 5: Item Details
```

### View-Only Menu
```
Level 1: Page Title
Level 2: Categories + Collections
Level 3: Items (Image-first)
Level 4: Item Details (Modal)
Level 5: Call-to-Action ("Place Order")
```

---

## ✨ Accessibility Features

- ✅ Color contrast ratios meet WCAG AA standards
- ✅ Large touch targets (48x48px minimum)
- ✅ Clear focus states on interactive elements
- ✅ Semantic HTML for screen readers
- ✅ Keyboard navigation support
- ✅ ARIA labels where needed
- ✅ Loading states clearly indicated
- ✅ Form inputs clearly labeled

---

## 📸 Visual Examples

### Item Card Anatomy
```
┌──────────────────────┐
│   [Image]            │  ← Lazy-loaded, 3:2 ratio
│                      │
├──────────────────────┤
│ Item Name      (bold)│  ← Font: 14px, 600 weight
│ Description    (gray)│  ← Font: 12px, 400 weight, 2 lines max
├──────────────────────┤
│ ₦2,500      [+-  +-] │  ← Price left, qty controls right
└──────────────────────┘
```

### Modal Anatomy
```
┌─────────────────────────────┐
│ Title              [X Close]│  ← 20px font, bold, right-aligned close
├─────────────────────────────┤
│                             │
│  Content Area               │  ← Scrollable if needed
│  (scrollable if long)       │  ← Max height: 90vh
│                             │
├─────────────────────────────┤
│ [Cancel]    [Confirm/Action]│  ← Usually: Cancel (gray) + Primary (black)
└─────────────────────────────┘
```

---

## 🎓 Design Principles Applied

1. **Clarity** - Clear visual hierarchy and purpose
2. **Consistency** - Same styles across all menus
3. **Efficiency** - Minimal clicks to complete action
4. **Feedback** - Visual feedback for all interactions
5. **Accessibility** - Usable by everyone
6. **Delight** - Smooth animations and polish
7. **Performance** - Fast load times, smooth scrolling
8. **Responsiveness** - Works on all screen sizes

---

**Design System Version**: 1.0
**Last Updated**: March 20, 2026
**Status**: ✅ Production Ready
