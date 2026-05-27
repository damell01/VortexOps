# Changelog

Use this file as the simple running update log for the app.

Format suggestion:

```md
## YYYY-MM-DD

- Short summary of what changed
- Deployment note if needed
- Follow-up or risk note if needed
```

---

## 2026-05-27

- Improved admin performance by disabling AI by default, lazy-loading heavy UI pieces, reducing eager-loading in Filament resources, and caching sidebar badge counts.
- Added review-related database indexes and fixed strict MySQL `GROUP BY` issues affecting dashboard widgets.
- Reworked the admin UI toward a more enterprise layout with fuller-width tables, flatter cards, a darker branded sidebar, and a custom responsive dashboard widget grid.
- Removed the old floating feedback control from the admin shell and moved Tour + Review Mode controls into a shared top-right action rail.
- Hardened review-mode saving and screenshot flows, and widened stored page URL columns for review/feedback records.
- Added bundled Vortex Breaks branding defaults, including the default logo asset and aqua-forward color palette.

Deployment notes:

- Run `php artisan migrate --force` if new migrations were added.
- Run `npm run build` after UI or review-mode changes.
- Run `php artisan optimize:clear && php artisan optimize && php artisan filament:optimize` after deploy.
