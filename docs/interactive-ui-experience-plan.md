# VortexOps Interactive UI — Experience Plan

## Design direction
Professional operations software with collectible-card-inspired moments. Light mode default, dark mode supported. Keep the existing top navigation, fast workflows, clear labels and compact layouts. Never make staff wait for a decorative animation before performing their work.

## Phase 1 — Sign-in pack reveal
- Password entry animates a thin energy bar beneath the field; purely visual, not a strength/correctness indicator. Sign-in is enabled based on required inputs, not the bar.
- Sign-in CTA is styled as a sealed foil pack. On successful authentication, a short tear/foil burst reveals a randomized holo-style welcome card, then routes to the user's permitted dashboard.
- Authentication errors appear normally and never reveal a card. Prevent double submits; show loading state.
- Provide a skip-animation preference, reduced-motion alternative, and a <=1-second default reveal. Do not use copyrighted card art or existing trading-card brand designs.
- Build with CSS transforms and lightweight SVG particles; no heavyweight 3D runtime.

## Phase 2 — Shared microinteraction system
- Tokens: subtle glow, foil gradient, rarity accent, motion durations, easing, focus ring, success/warning/error states.
- Card hover/keyboard focus: gentle lift and optional tilt on nonessential tiles. No tilt on dense data tables.
- Numbers animate only on changes; preserve legible static numbers and reduced-motion behavior.
- Toasts: quick holo shimmer for successful actions; explicit text and accessible live regions.
- Provide an admin/user toggle for motion intensity: Off, Subtle, Full.

## Phase 3 — Operational screens
- Inventory: successful barcode scan pulse; unknown barcode action sheet; compact item card preview with photo, quantity, cost, location and recent changes; no misleading rarity for ordinary SKUs.
- Fulfillment: station tiles animate on status transitions; progress ring for packages packed; clear error/review states for missing or damaged items; avoid distracting celebrations during packing.
- Streamers/shows: flip-style show detail card on deliberate click; animated timeline of show states; profit/share figures remain plain and auditable.
- Payroll: subtle completion animation only after a successful server-confirmed finalization; never obscure totals or confirmation dialogs.
- Messaging: restrained unread dot animation; preview reveal on deliberate interaction; no pulsing notification loop.
- Reports: hover highlights, smooth filtering, skeletons for real pending loads; never use animation to mask slow SQL queries.

## Phase 4 — Optional playful features
- Collectible-themed badges for voluntary milestones (not used for compensation, ranking, or employee evaluation).
- Random holo welcome card variants: silver, rainbow, gold. Visual rarity only, no gambling mechanics or rewards.
- Easter eggs for owner-only experiences; not on critical operational paths.

## Accessibility, performance and security acceptance
- WCAG 2.2 AA target, keyboard support, visible focus, contrast, screen-reader text, reduced-motion support, no flashing effects.
- Keep effects compositor-friendly (transform/opacity); test mobile and low-end devices. Avoid layout shift and unnecessary network requests.
- Respect authentication/session security and permissions; don't imply that the password indicator verifies a password.
- Feature-flag the sign-in reveal and ship in stages with rollback capability.
- Measure actual page navigation latency separately; animation should never delay real app performance.

## Suggested build order
1. Design tokens + motion preferences
2. Sign-in prototype + accessible fallback
3. Scan feedback + dashboard tiles
4. Fulfillment station status interactions
5. Payroll/report polish
6. Optional achievement visuals

## Decisions to make
- Brand palette: retain VortexOps colors; light by default.
- Sign-in reveal frequency: first login of day vs every login (recommend first login of day, skippable).
- Whether holo art is custom abstract artwork or user-specific channel cards.
