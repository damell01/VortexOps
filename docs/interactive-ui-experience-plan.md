# Current approved scope — November 9, 2026

**Active together:** Phase 1 Ledger Tip Totals/attribution review and Phase 3 admin dashboard usability. **Removed:** pack-opening sign-in, password charge, holo login reveal. **Excluded:** barcode scanning feedback. These exclusions supersede older ideas below.

## Workstream A — Ledger
1. Improve readability and explain unverified business vs streamer/show attribution (initial UI changes committed).
2. Build review-focused queue using existing ledger attribution and approval workflow; inspect existing filters/actions before changing them.
3. Display why each match is unresolved, evidence source, candidate business/show/streamer and approval status. Never silently approve weak matches.
4. Ensure count and net totals reconcile across filters, channels and imported statuses; preserve two-person approval and immutable finalized payroll.
5. Add targeted tests, accessibility/mobile checks and staging verification.

## Workstream B — Admin dashboard
1. Compact first screen: prioritized KPI cards, quick actions and needs-attention summary.
2. Expand/collapse detail panels on deliberate interaction; light mode default, dark mode supported.
3. Subtle hover/focus transitions and optional stat transitions with reduced-motion support; no distracting foil effects on finance data.
4. Improve mobile responsive layout, skeletons for actual loading and avoid expensive repeated database queries.
5. Validate permissions, performance, and browser behavior on staging.

## Future work after A and B
Inventory expanding item cards (no scanner feedback); fulfillment station status/progress; show analytics timeline; payroll completion confirmation; messaging previews (feature remains off); report filtering/charts.

## Release gates
No automatic payroll changes, bulk ledger rematches, model or schema changes without review. Stage and verify before production rollout.

---

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
