<?php

// Production starts with a privileged-only pilot. It is independent of the
// employee rollout switch, so a previously disabled team rollout stays off.
// Set VORTEX_PRIVILEGED_COMMUNICATIONS_PREVIEW=false to turn off the pilot.
$teamMaster = (bool) env('VORTEX_COMMUNICATIONS_ENABLED', false);
$privilegedPreview = (bool) env('VORTEX_PRIVILEGED_COMMUNICATIONS_PREVIEW', app()->environment('production'));
$teamEnabled = $teamMaster && ! $privilegedPreview && (bool) env('VORTEX_CHATIFY_TEAM_ENABLED', false);
$available = $teamMaster || $privilegedPreview;

return [
    'available' => $available,
    'privileged_preview_enabled' => $privilegedPreview,
    // Only the owner, super admins and standard admins may use the pilot.
    'enabled' => $available && ($privilegedPreview || (bool) env('VORTEX_CHATIFY_PREVIEW_ENABLED', false)),
    // No streamers, fulfillment staff, or other employees during the pilot.
    'team_enabled' => $teamEnabled,
    // Pilot messages must not trigger employee email or push alerts.
    'message_notifications_enabled' => $teamEnabled && (bool) env('VORTEX_CHATIFY_MESSAGE_NOTIFICATIONS_ENABLED', false),
];
