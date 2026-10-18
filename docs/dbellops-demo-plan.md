# DBellOps Demo Workspace

Working brand: **DBellOps** — by DBell Creations.

Purpose: Create an isolated, fictional-data demonstration of seller inventory, show analytics, costs and profitability for a Whatnot-seller interest-validation video.

## Safety rules
- This branch is based on `Production`; do not deploy it to the VortexOps production environment.
- Never copy real customer, order, account, token, session, or financial records into demo fixtures.
- Use an isolated database, environment, and storage bucket/path for demo runs.
- Do not trigger live Whatnot scraping, webhooks, emails, billing, or external API writes from the demo.
- Do not promise independent-seller automatic sync or imply official Whatnot partnership.
- Do not expose payroll, employee-management, or other enterprise-only features in demo footage.

## Demo storyline (75 seconds)
1. 0–8s: “I built this for a Whatnot business. Would other sellers use it?”
2. 8–22s: Seller dashboard showing fictional sales, costs and profit.
3. 22–38s: One show’s revenue, fees, COGS, giveaways and expenses.
4. 38–50s: Inventory, cost tracking, search and stock.
5. 50–63s: Monthly reports and sales trends.
6. 63–75s: “Thinking about offering this to other Whatnot sellers. What would you pay?”

## Next implementation milestones
1. Audit existing models, resources, permissions and any data dependencies.
2. Add demo-only configuration and safe sample-data seeders with no external integrations.
3. Add DBellOps demo branding and seller-focused navigation, gated to demo mode.
4. Verify financial calculations against seeded sample figures.
5. Capture approved screens with Playwright and assemble MP4 with a reproducible editing project.
6. Verify no private data appears and no production deployment was changed.

This document describes planned work; it does not claim these features or the video are completed.
