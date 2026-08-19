<?php

return [

    'whatnot' => [
        'email'    => env('WHATNOT_EMAIL'),
        'password' => env('WHATNOT_PASSWORD'),
        'node_bin' => env('WHATNOT_NODE_BIN', 'node'),
        'limit'    => (int) env('WHATNOT_IMPORT_LIMIT', 50),
        'playwright_browsers_path'         => env('PLAYWRIGHT_BROWSERS_PATH'),
        'playwright_chromium_executable'   => env('PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH'),

        // Pauses only the scheduled whatnot:* jobs. They all drive one Chromium
        // profile behind one lock, so while any of them is running a manual run
        // just queues — which makes the scraper the one thing you cannot debug
        // by hand at the moment you most need to. Stopping the whole scheduler
        // instead would also stop backups, health checks and reports.
        'schedule_enabled' => filter_var(env('WHATNOT_SCHEDULE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    ],

];
