<?php

namespace App\Support;

/**
 * What each screen's guided tour says, and which element each line points at.
 *
 * Tours live here as data rather than inside the pages they describe, for two
 * reasons. Steps are copy — they get reworded far more often than the screens
 * do, and hunting through Filament resources to fix a sentence is how
 * documentation stops being maintained. And keeping them together makes the set
 * reviewable: you can read every tour in the app in one sitting and notice that
 * two screens explain the same idea differently.
 *
 * A step's `el` is a CSS selector against the rendered page. Selectors that
 * match nothing are dropped before the tour starts rather than pointing at
 * empty space — see the note on filtering in tourFor().
 */
class GuidedTours
{
    /**
     * Steps keyed by tour id.
     *
     * @return array<string, array{title: string, steps: array<int, array<string, string>>}>
     */
    public static function definitions(): array
    {
        return [
            'inventory-list' => [
                'title' => 'All Inventory',
                'steps' => [
                    [
                        'title' => 'Everything you stock',
                        'body'  => 'One row per item. The number you have is stock; the row itself is the item. Adding a second row for something you already stock is the mistake this page exists to prevent.',
                    ],
                    [
                        'el'    => '.fi-ta-search-field, .fi-input-wrp:has(input[type="search"])',
                        'title' => 'Search before you add',
                        'body'  => 'Search by name, SKU or barcode. If it turns up here, add stock to it instead of creating a new item.',
                    ],
                    [
                        'el'    => '.fi-ta-header-toolbar .fi-btn, .fi-header-heading + * .fi-btn',
                        'title' => 'Adding something new',
                        'body'  => 'Only for items that genuinely do not exist yet. Quick Add asks the minimum; the full form covers vendor, reorder points and notes.',
                    ],
                    [
                        'el'    => '.fi-ta-filters-trigger, .fi-ta-actions',
                        'title' => 'Narrowing the list',
                        'body'  => 'Filter by location or category when you are counting one shelf rather than browsing everything.',
                    ],
                ],
            ],

            'inventory-quick-add' => [
                'title' => 'Quick Add',
                'steps' => [
                    [
                        'title' => 'Three steps, one required field',
                        'body'  => 'Only the item name is required. Everything else can be filled in later from the item page.',
                    ],
                    [
                        'el'    => 'input[wire\\:model="data.name"]',
                        'title' => 'Name it as you would say it',
                        'body'  => 'Use the name you would use out loud — "2024 Topps Chrome Hobby Box". This is what everyone searches for later.',
                    ],
                    [
                        'el'    => '#quickadd-scan-btn',
                        'title' => 'Scan the barcode',
                        'body'  => 'Opens the camera and fills the barcode field. A USB scanner gun works too — click into the field and scan. If the barcode is already on another item, that item already exists.',
                    ],
                    [
                        'el'    => 'input[wire\\:model="data.sku"]',
                        'title' => 'SKU is optional',
                        'body'  => 'Leave it blank if you do not have one. Blank is fine on any number of items.',
                    ],
                ],
            ],

            'inventory-locations' => [
                'title' => 'Locations',
                'steps' => [
                    [
                        'title' => 'Places stock can sit',
                        'body'  => 'Nothing else in inventory works until one exists — an empty location dropdown elsewhere in the app usually means this page is empty.',
                    ],
                    [
                        'el'    => '.fi-ta-header-toolbar .fi-btn',
                        'title' => 'The general one is Main Storage',
                        'body'  => 'There is no type called "warehouse". Main Storage is the general-purpose shelf. Create it if you cannot find it.',
                    ],
                    [
                        'el'    => '.fi-ta-table tbody tr:first-child',
                        'title' => 'Type is not decoration',
                        'body'  => 'Streamer Inventory locations are filtered per streamer — a streamer sees only their own. Naming a shared shelf that way hides it from everybody else.',
                    ],
                ],
            ],

            'pallet-view' => [
                'title' => 'Receiving a pallet',
                'steps' => [
                    [
                        'title' => 'The pallet is the workspace',
                        'body'  => 'Everything about this delivery lives here — expected items, photos, paperwork and the costs that decide what the stock is worth.',
                    ],
                    [
                        'el'    => '[data-tour="pallet-lines"], .fi-section:has(table)',
                        'title' => 'Expected items',
                        'body'  => 'One line per product on the pallet. Add them by name as you read the packing slip; the item does not need to exist yet.',
                    ],
                    [
                        'el'    => '[data-tour="pallet-scan"]',
                        'title' => 'Scan as you unload',
                        'body'  => 'Scan each case to receive it, or use Receive All on a line you have counted by hand.',
                    ],
                    [
                        'el'    => '[data-tour="pallet-costs"]',
                        'title' => 'Freight belongs on the pallet',
                        'body'  => 'Shipping and fees entered here spread across the lines, which is what makes landed cost correct. Adding boxes one at a time elsewhere loses this.',
                    ],
                ],
            ],

            'payouts' => [
                'title' => 'Payouts',
                'steps' => [
                    [
                        'title' => 'What each streamer is owed',
                        'body'  => 'One row per payout. The type decides how it is calculated — profit share, hourly, PWE and labels, or a flat rate.',
                    ],
                    [
                        'el'    => '.fi-ta-filters-trigger, .fi-ta-actions',
                        'title' => 'Work one week at a time',
                        'body'  => 'Filter to the week you are paying. Batches group a week of payouts so they can be approved and paid together.',
                    ],
                    [
                        'el'    => '.fi-ta-table tbody tr:first-child',
                        'title' => 'Check before approving',
                        'body'  => 'Open a payout to see the shows and figures behind the number. Approval is the point it becomes a real obligation.',
                    ],
                ],
            ],
        ];
    }

    /**
     * Which screen shows which tour.
     *
     * Keyed by Filament's route name rather than by page class, so a tour is
     * attached without editing the page — resource List and Edit pages are
     * Filament's own classes, and adding a trait to each of them to hold one
     * string is a lot of edits to maintain a lookup table that reads better in
     * one place anyway.
     *
     * @return array<string, string>
     */
    public static function routeMap(): array
    {
        return [
            'filament.admin.resources.inventory-items.index'     => 'inventory-list',
            'filament.admin.resources.inventory-items.quick-add' => 'inventory-quick-add',
            'filament.admin.resources.inventory-locations.index' => 'inventory-locations',
            'filament.admin.resources.pallets.view'              => 'pallet-view',
            'filament.admin.resources.payouts.index'             => 'payouts',
        ];
    }

    /**
     * The tour for the current route, already marked with whether it should
     * open itself for this viewer.
     *
     * @return array<string, mixed>|null
     */
    public static function forRoute(?string $routeName, ?object $user = null): ?array
    {
        $tour = static::tourFor(static::routeMap()[$routeName] ?? null);

        if ($tour === null) {
            return null;
        }

        // Still offered after it has been seen — the launcher stays in the
        // header — but it stops opening itself.
        $seen = $user?->completed_tours ?? [];

        return [...$tour, 'auto' => ! in_array($tour['id'], $seen, true)];
    }

    /**
     * The tour for a page, or null when it has none.
     *
     * @return array{id: string, title: string, steps: array<int, array<string, string>>}|null
     */
    public static function tourFor(?string $id): ?array
    {
        if ($id === null) {
            return null;
        }

        $tour = static::definitions()[$id] ?? null;

        return $tour === null ? null : ['id' => $id, ...$tour];
    }

    /** Every tour id, for validating what a page asks for. */
    public static function ids(): array
    {
        return array_keys(static::definitions());
    }
}
