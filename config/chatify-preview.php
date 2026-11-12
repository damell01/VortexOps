<?php

// Production defaults to an owner-only preview. Team-wide Chatify remains disabled.
$available = (bool) env('VORTEX_COMMUNICATIONS_ENABLED', env('APP_ENV') === 'production');

return [
    // Master switch: messages, announcements, previews, endpoints and alerts.
    'available' => $available,
    // Never enable until Chatify's auth, uploads, broadcasting and routes pass QA.
    'team_enabled' => $available && env('VORTEX_CHATIFY_TEAM_ENABLED', false),
    'enabled' => $available && env('VORTEX_CHATIFY_PREVIEW_ENABLED', env('APP_ENV') === 'production'),
    'message_notifications_enabled' => $available && env('VORTEX_CHATIFY_MESSAGE_NOTIFICATIONS_ENABLED', false),
];
