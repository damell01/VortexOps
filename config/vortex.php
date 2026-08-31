<?php

return [

    'whatnot' => [
        'email'    => env('WHATNOT_EMAIL'),
        'password' => env('WHATNOT_PASSWORD'),
        'node_bin' => env('WHATNOT_NODE_BIN', 'node'),
        'limit'    => (int) env('WHATNOT_IMPORT_LIMIT', 50),
        // Mirrors the resolution in scripts/whatnot-scraper.cjs, which honours
        // this before falling back to storage/. Declared here so PHP can name
        // the same file the scraper will actually read: env() outside a config
        // file returns null the moment the config is cached.
        'cookies_file' => env('WHATNOT_COOKIES_FILE'),

        // Browser runtime: local keeps the existing VPS Chromium path; steel
        // connects Playwright to the self-hosted Steel service over CDP.
        'browser_backend' => env('WHATNOT_BROWSER_BACKEND', 'local'),
        'steel_base_url'  => env('STEEL_BASE_URL', 'http://127.0.0.1:3000'),

        'playwright_browsers_path'         => env('PLAYWRIGHT_BROWSERS_PATH'),
        'playwright_chromium_executable'   => env('PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH'),

        // How long a run waits for the shared browser lock before giving up.
        //
        // Twenty minutes is right in production, where the queue behind the
        // lock is another real scrape that will finish. It is exactly wrong in
        // a test suite: one lock left held turns every later test that touches
        // the scraper into a twenty-minute stall, and a suite that hangs looks
        // identical to one that is still working. phpunit.xml sets this to a
        // couple of seconds so a stuck lock fails and says so.
        'browser_lock_wait' => (int) env('WHATNOT_BROWSER_LOCK_WAIT', 1200),

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

        // Default to a windowed Chromium session. The production command runner
        // automatically supplies a private Xvfb display when cron/queue has no
        // DISPLAY, so this works unattended too. Headless remains available as
        // an explicit opt-in, but the live Whatnot test showed its analytics
        // navigation being challenged while the authenticated Seller Hub itself
        // was healthy.
        'headless' => filter_var(env('WHATNOT_HEADLESS', false), FILTER_VALIDATE_BOOLEAN),
    ],

];
