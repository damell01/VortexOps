<?php

return [
    // Never enable until Chatify's auth, uploads, broadcasting and routes pass QA.
    // Explicit, separate rollout switch for employees; owner preview remains isolated.
    'team_enabled' => env('VORTEX_CHATIFY_TEAM_ENABLED', false),
    'enabled' => env('VORTEX_CHATIFY_PREVIEW_ENABLED', false),
    // Separate opt-in: disabled until recipient membership and delivery QA pass.
    'message_notifications_enabled' => env('VORTEX_CHATIFY_MESSAGE_NOTIFICATIONS_ENABLED', false),
];
