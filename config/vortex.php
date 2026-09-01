<?php

return [

    'whatnot' => [
        'email'    => env('WHATNOT_EMAIL'),
        'password' => env('WHATNOT_PASSWORD'),
        'node_bin' => env('WHATNOT_NODE_BIN', 'node'),
        'limit'    => (int) env('WHATNOT_IMPORT_LIMIT', 50),

        // Backend selected by scripts/whatnot-runner.cjs. 'local' (default) is the
        // existing Node/Playwright scraper, unchanged. 'scrapling' routes supported
        // modes to scripts/whatnot-scrapling.py — see CLAUDE.md "Whatnot scraper".
        'browser_backend' => env('WHATNOT_BROWSER_BACKEND', 'local'),
        'python_bin'      => env('WHATNOT_PYTHON_BIN', 'python3'),
        'headless'        => env('WHATNOT_HEADLESS', true),
    ],

];
