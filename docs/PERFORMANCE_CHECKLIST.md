# Performance Checklist

Use this checklist before and after each performance change so we compare the same flows.

## Setup

- Use the same browser, same machine, and same internet connection for both runs.
- Open DevTools Network tab and enable `Disable cache`.
- If possible, use a test account with the same permissions each time.
- Clear Laravel caches once before the first run: `php artisan optimize:clear`.

## Admin Flows To Measure

- Login to `/admin`.
- Navigate from Dashboard to `Shows`.
- Open one `Show` record, then go back to the list.
- Navigate from `Shows` to `Review Items`.
- Open one `Review Item`, run one quick action, and return to the list.
- Open the AI assistant for the first time on a page.

## What To Record

- Time to first visible page update after clicking a sidebar link.
- Total request time for the slowest `livewire/update` or page request.
- Number of SQL queries for the page if Laravel Debugbar or Telescope is available.
- Whether any request waits on `api/tags` or other Ollama endpoints.
- Peak memory for the slow pages if your profiler shows it.

## Good Signs After The Fix

- Normal page-to-page navigation should no longer wait on Ollama requests.
- `Review Items` list should stop loading comment threads it does not render.
- `Shows` list should avoid loading approval lines and payouts until record view.
- The first AI panel open may still take time if Ollama is offline, but normal app navigation should stay responsive.

## If It Is Still Slow

- Run `php artisan optimize`.
- Confirm `APP_ENV=production` and `APP_DEBUG=false` outside local development.
- Move `CACHE_STORE` and `SESSION_DRIVER` away from `database` if request volume is growing.
- Profile the slowest SQL queries with your database logs or Laravel Telescope.
