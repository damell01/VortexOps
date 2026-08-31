<?php

return [

    'whatnot' => [
        'email'    => env('WHATNOT_EMAIL'),
        'password' => env('WHATNOT_PASSWORD'),
        'node_bin' => env('WHATNOT_NODE_BIN', 'node'),
        'python_bin' => env('WHATNOT_PYTHON_BIN', 'python3'),
        'limit'    => (int) env('WHATNOT_IMPORT_LIMIT', 50),
        // Mirrors the resolution in scripts/whatnot-scraper.cjs, which honours
        // this before falling back to storage/. Declared here so PHP can name
        // the same file the scraper will actually read: env() outside a config
        // file returns null the moment the config is cached.
        'cookies_file' => env('WHATNOT_COOKIES_FILE'),

        // Browser runtime. `scrapling` uses scripts/whatnot-scrapling.py with the
        // same persistent Chrome profile and the same PHP import/persistence
        // pipeline. `local` keeps the existing Node/Playwright engine; `steel`
        // keeps the existing self-hosted Steel/CDP option.
        'browser_backend' => env('WHATNOT_BROWSER_BACKEND', 'local'),
        'steel_base_url'  => env('STEEL_BASE_URL', 'http://127.0.0.1:3000'),

        'playwright_browsers_path'         => env('PLAYWRIGHT_BROWSERS_PATH'),
        'playwright_chromium_executable'   => env('PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH'),

        // How long a run waits for the shared browser lock before giving up.
        'browser_lock_wait' => (int) env('WHATNOT_BROWSER_LOCK_WAIT', 1200),

        // Sanity boundary for Upcoming shows.
        'max_upcoming_days' => (int) env('WHATNOT_MAX_UPCOMING_DAYS', 120),

        // Pauses only the scheduled whatnot:* jobs.
        'schedule_enabled' => filter_var(env('WHATNOT_SCHEDULE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

        // Optional egress setting retained for the existing Node/Steel engines.
        'proxy' => env('WHATNOT_PROXY'),

        // Default to a windowed browser session. The production command runner
        // supplies Xvfb when a Linux headed browser has no DISPLAY.
        'headless' => filter_var(env('WHATNOT_HEADLESS', false), FILTER_VALIDATE_BOOLEAN),
    ],

];
