<?php

return [

    'whatnot' => [
        'email'    => env('WHATNOT_EMAIL'),
        'password' => env('WHATNOT_PASSWORD'),
        'node_bin' => env('WHATNOT_NODE_BIN', 'node'),
        'limit'    => (int) env('WHATNOT_IMPORT_LIMIT', 50),
        'playwright_browsers_path'         => env('PLAYWRIGHT_BROWSERS_PATH'),
        'playwright_chromium_executable'   => env('PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH'),
    ],

];
