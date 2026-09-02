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

        // Browser runtime. `scrapling` routes through the StealthySession adapter
        // and attaches to the existing persistent Chrome over CDP. `local` keeps
        // the existing Node/Playwright engine; `steel` keeps the self-hosted
        // Steel/CDP option.
        'browser_backend' => env('WHATNOT_BROWSER_BACKEND', 'local'),
        'steel_base_url'  => env('STEEL_BASE_URL', 'http://127.0.0.1:3000'),

        // Scrapling runtime options are configuration, not hard-coded behavior.
        // Keeping them here is important because Laravel may cache config and
        // does not guarantee raw .env values are visible to child processes.
        'scrapling_use_cdp' => filter_var(env('WHATNOT_SCRAPLING_USE_CDP', true), FILTER_VALIDATE_BOOLEAN),
        'scrapling_cdp_url' => env('WHATNOT_SCRAPLING_CDP_URL', 'http://127.0.0.1:9222'),
        'scrapling_solve_cloudflare' => filter_var(env('WHATNOT_SCRAPLING_SOLVE_CLOUDFLARE', false), FILTER_VALIDATE_BOOLEAN),
        'scrapling_block_webrtc' => filter_var(env('WHATNOT_SCRAPLING_BLOCK_WEBRTC', false), FILTER_VALIDATE_BOOLEAN),
        'scrapling_hide_canvas' => filter_var(env('WHATNOT_SCRAPLING_HIDE_CANVAS', false), FILTER_VALIDATE_BOOLEAN),
        'scrapling_allow_webgl' => filter_var(env('WHATNOT_SCRAPLING_ALLOW_WEBGL', true), FILTER_VALIDATE_BOOLEAN),
        'scraper_fallback' => filter_var(env('WHATNOT_SCRAPER_FALLBACK', false), FILTER_VALIDATE_BOOLEAN),

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
