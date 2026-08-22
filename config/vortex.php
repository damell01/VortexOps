<?php

return [

    'whatnot' => [
        'email'    => env('WHATNOT_EMAIL'),
        'password' => env('WHATNOT_PASSWORD'),
        'node_bin' => env('WHATNOT_NODE_BIN', 'node'),
        'limit'    => (int) env('WHATNOT_IMPORT_LIMIT', 50),
        'playwright_browsers_path'         => env('PLAYWRIGHT_BROWSERS_PATH'),
        'playwright_chromium_executable'   => env('PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH'),

        // Sanity boundary for Upcoming shows. The Seller Hub should not be able
        // to create malformed year-out rows in VortexOps. Four months still
        // leaves plenty of room for legitimate advance scheduling.
        'max_upcoming_days' => (int) env('WHATNOT_MAX_UPCOMING_DAYS', 120),

        // Pauses only the scheduled whatnot:* jobs. They all drive one Chromium
        // profile behind one lock, so while any of them is running a manual run
        // just queues — which makes the scraper the one thing you cannot debug
        // by hand at the moment you most need to. Stopping the whole scheduler
        // instead would also stop backups, health checks and reports.
        'schedule_enabled' => filter_var(env('WHATNOT_SCHEDULE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

        // Egress for Whatnot traffic only. Cloudflare blocks this server by IP —
        // datacenter ASNs get judged far harder than residential ones — and no
        // amount of browser tuning changes an IP. Pointing this at a SOCKS proxy
        // that exits through a residential connection is what gets past it.
        // Example: socks5://127.0.0.1:1080
        'proxy' => env('WHATNOT_PROXY'),

        // Null means "leave it to the scraper's own default" so the env var
        // still works when set in the shell rather than in .env.
        'headless' => env('WHATNOT_HEADLESS') === null
            ? null
            : filter_var(env('WHATNOT_HEADLESS'), FILTER_VALIDATE_BOOLEAN),
    ],

];
