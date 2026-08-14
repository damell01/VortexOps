<?php

/**
 * Overrides Filament's global-search strings. Only the placeholder differs
 * from the package default — "Search" alone gave no hint about what the
 * topbar field actually searches.
 */

return [

    'field' => [
        'label' => 'Global search',
        'placeholder' => 'Search items, SKU, or description...',
    ],

    'no_results_message' => 'No search results found.',

];
