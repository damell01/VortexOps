# VortexOps messaging performance and UI rollout

## Current implementation (Dev)

- Conversations now check a small, authenticated **revision token** every ~4 seconds instead of repeatedly transferring and rendering the latest 50 messages. The token contains no message content. Only participants can access it.
- Eloquent Chatify message create/update/delete and reaction writes publish a new random revision token **after database commit**. The client reloads messages when the token changes.
- A full message fetch still occurs every 60 seconds as a safety fallback for cache eviction, external/raw SQL writes, or missed observers. If the revision endpoint fails, full-message polling falls back to 15 seconds.
- Inbox unread counts refresh every 15 seconds with the messenger open and every 45 seconds while closed, only on a visible, online page. Same-browser tabs notify each other after reads and sends.
- Presence refreshes every ~6.5 seconds when a conversation is open. Typing state remains cache-backed and short-lived.
- Reactions are optimistic, with rollback and a visible error on failed POST. Failed outgoing text messages have a Retry action.
- Group membership, renaming, role changes, and adding/removing participants use the existing authenticated Chatify APIs and permission checks.
- File attachments show names, size when available, and download links. Images and **same-origin** PDFs have previews.

## Staging verification before Production

1. Confirm the revision endpoint returns HTTP 200 only for a member, HTTP 404 for an authorized non-member, and HTTP 403 when messaging is disabled.
2. In two different accounts, send, edit, delete, and react to messages. Confirm the other account sees changes without manually refreshing, including reactions.
3. With DevTools Network open, confirm the revision endpoint is polled while a conversation is open and the full message endpoint is *not* called every four seconds when idle.
4. Leave a conversation open for over a minute: confirm the full-refresh fallback runs. Disable the revision endpoint temporarily in Dev and confirm messages still arrive via fallback.
5. Test unread badges across tabs after reading, and test closed-drawer unread counts. Confirm browser notification permission is not requested automatically.
6. Verify group creation, viewing members, renaming, role changes, adding/removing people, and permission failures for regular members.
7. Test optimistic reaction rollback with a forced 500 response; test failed message Retry without duplicates. Test images, PDFs, other files, and invalid attachment URLs.
8. Verify dark mode, narrow mobile widths, keyboard Escape behavior, tab focus, and screen-reader labels.
9. Run `php artisan test --filter=Chat` and the full reliability suite, then verify no new production scheduler or message alerts.

## Real-time WebSocket rollout (separate infrastructure stage)

This change does **not** claim true push delivery across devices. It uses efficient polling and BroadcastChannel within one browser profile.

To enable true cross-device push safely, provision a Laravel Reverb server (or another authenticated broadcasting provider) in the staging environment, add the supported Laravel broadcasting and Echo dependencies with a lockfile update, configure secure WSS behind nginx, and authorize conversation-private channels by server-side membership. Broadcast only opaque conversation-change events (never full message content). Keep revision polling as a fallback, and test reconnection, logout, membership removal, cross-channel isolation, mobile battery usage, and rate limiting before enabling it in Production.

Do not enable `VORTEX_CHATIFY_TEAM_ENABLED` or `VORTEX_CHATIFY_MESSAGE_NOTIFICATIONS_ENABLED` on Production as part of this UI/performance change. Those rollout switches remain controlled separately.
