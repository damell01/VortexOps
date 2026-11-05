# Team communications: staging verification checklist

Do not enable the employee rollout or notification delivery on production until the checks below pass on staging. This document is a test plan, not evidence that tests have run.

## Prerequisites
- Back up the staging database and verify migrations on staging only.
- Run `php artisan migrate --force` on staging and confirm `chat_message_reactions` and `team_announcement_acknowledgments` exist.
- Run `php artisan test --filter=Chat` and `php artisan test --filter=Announcement`; inspect failures rather than assuming success.
- Confirm Chatify package migrations, routes, authorization policies, queue worker, mail and web-push credentials.
- Set `VORTEX_CHATIFY_PREVIEW_ENABLED=true` on staging only; keep `VORTEX_CHATIFY_TEAM_ENABLED=false` and `VORTEX_CHATIFY_MESSAGE_NOTIFICATIONS_ENABLED=false` initially. Clear cached configuration after changes.

## Messaging access
- Owner, admin, streamer, and assistant accounts: verify access to only conversations they belong to.
- Regular employees can create direct and manually selected group conversations; only admin/owner can select recipients by global role.
- Verify group owner, admin, moderator, and member actions against backend authorization.
- Confirm removed participants cannot read history through API, reaction endpoint, or attachment links.
- Test unauthorized reaction requests, batch lookups, and soft-deleted messages.

## Messages
- Two browsers and two mobile devices: send, reply, edit, delete, paginate, search, mark read, and attach files.
- Reactions: toggle all allowed emoji, reload, and verify counts, membership checks and concurrent toggles.
- Mentions: type @ and choose teammate; Enter should select the suggestion, Escape should close it, Shift+Enter should insert a newline. Verify notifications only for the intended member.
- Confirm live updates and fallback polling; don't claim real-time delivery until broadcasting is configured and tested.
- Check mobile scrolling, keyboard focus, modals, dark mode, and default light mode.

## Announcements
- Owner preview: create draft, schedule, publish preview, edit, archive, and filter by roles/users.
- Verify a targeted banner never appears to a user outside the intended audience.
- Acknowledge once, reload, confirm timestamp and count; cannot dismiss until acknowledged.
- Edit and re-publish: acknowledgments should reset and require new confirmation.
- Check the owner report for correct names, times and totals.
- Owner preview is not an employee-facing announcement rollout. Do not confuse the two.

## Notification delivery
- Enable `VORTEX_CHATIFY_MESSAGE_NOTIFICATIONS_ENABLED=true` on staging only after all access tests pass.
- Explicit push/email opt-in, preview off, quiet hours, read-before-send, membership removal, and rate limiting.
- Verify message and mention notifications across different recipient accounts and browsers.
- Inspect queue retries, duplicate prevention, invalid push subscriptions and mail delivery failures without logging message contents.

## Rollout
- Enable `VORTEX_CHATIFY_TEAM_ENABLED=true` on staging only after permission tests pass.
- Re-test with representative roles and mobile clients before any production change.
- Have a rollback plan: disable team and notification flags first; preserve audit and message data.
