# DBellOps initial code audit

Reviewed branch: `feature/dbellops-demo` (based on `Production`).

## Confirmed reusable application components
- `app/Filament/Resources/ShowResource.php`: show resource, order relations, access rules and streamer-specific workflows.
- `app/Filament/Resources/InventoryItemResource.php`: inventory resource with stock handling and access rules.
- `resources/views/filament/pages/dashboard-improved.blade.php`: role-specific dashboard, including streamer/admin/fulfillment branches.
- `app/Providers/Filament/AdminPanelProvider.php`: settings-driven brand name/color/logo, shared panel navigation and authentication.
- `database/seeders/DatabaseSeeder.php`: calls `DemoDataSeeder` by default.

## Findings / blockers before using the live application for filming
1. Existing dashboard and show resource contain enterprise and streamer-specific concepts; hiding menu links alone will not remove these workflows.
2. Existing `DemoDataSeeder` is referenced by the default seeder; its contents and safety guards need review before invoking any seed command.
3. Panel branding can already be driven by settings, but the demo must use its own isolated settings and database.
4. Current resources appear built for a single operation. **Do not claim multi-tenant isolation** until model/query policies, jobs, exports and storage are audited and tested.
5. Existing scraper must remain disabled for demo footage; no independent-seller login automation or OAuth is being represented as available.

## Demo fixture
See `docs/dbellops-demo-fixture.json`. All figures are fictional. Profit equation: gross sales - fees - COGS - giveaways - other expenses. Summary: $18,420 - $2,210 - $7,820 - $460 - $1,130 = $6,800.

## Implementation order
1. Inspect model/migration fields and existing DemoDataSeeder before writing database seeders.
2. Build demo-only feature flag and isolated configuration; explicitly block external side effects.
3. Build seller-focused navigation and dashboard, without changing production behavior.
4. Test calculations, record screenshots, assemble video.
