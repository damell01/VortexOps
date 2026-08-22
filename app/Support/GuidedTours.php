<?php

namespace App\Support;

/** Central copy and selectors for the in-app guided tours. */
class GuidedTours
{
    public static function definitions(): array
    {
        return [
            'inventory-list' => [
                'title' => 'Inventory Center',
                'steps' => [
                    ['el' => '[data-tour="inventory-start"]', 'title' => 'Start with the job you are doing', 'body' => 'Quick Add is for a simple new product, Quick Scan is for something already in your hand, and Receive Shipment is for pallets or vendor deliveries. You do not need to start with a long form.'],
                    ['el' => '.fi-ta-search-field, .fi-input-wrp:has(input[type="search"])', 'title' => 'Search before creating another item', 'body' => 'Search by product name, SKU, or barcode first. If the item already exists, open it and change or move its stock instead of creating a duplicate product.'],
                    ['el' => '[data-tour="inventory-health"]', 'title' => 'Stock health at a glance', 'body' => 'These totals show what is in stock, low, or out. They use the same inventory visibility rules as the list below.'],
                    ['el' => '[data-tour="inventory-list"]', 'title' => 'Open an item for the full story', 'body' => 'The item page is where you see stock by location, movement history, receiving history, costs, aliases, and the actions that change this product.'],
                ],
            ],

            'inventory-item' => [
                'title' => 'Inventory Item',
                'steps' => [
                    ['title' => 'This page is the product record', 'body' => 'Use this page to understand what the product is, how much is on hand, where it is, what it cost, and how it got here. Quantity changes should go through Move / Correct Stock so history stays intact.'],
                    ['el' => '.fi-page-header-actions, .fi-header-actions', 'title' => 'Use actions for changes', 'body' => 'Edit changes the product itself. Add Stock books new stock in. Move / Correct Stock is for transfers, count corrections, giveaways, losses, and other quantity changes.'],
                    ['el' => 'button[wire\\:click*="setTab"]', 'title' => 'Use the tabs as history, not separate workflows', 'body' => 'Stock by Location answers where it is. Receiving shows which deliveries it came from. Movements is the audit trail. Cost tabs explain what the inventory cost is based on.'],
                    ['el' => '.fi-main, main', 'title' => 'Do not edit stock numbers directly', 'body' => 'If a physical count is wrong or inventory changes locations, use Move / Correct Stock. That records the before and after quantities and the reason for the change.'],
                ],
            ],

            'inventory-edit' => [
                'title' => 'Edit Inventory Item',
                'steps' => [
                    ['title' => 'Edit what the product is', 'body' => 'This form is for the product record: name, photo, SKU, barcode, case/container definition, classification, vendor, costs, reorder settings, and notes. Use the stock screen for quantity changes.'],
                    ['el' => 'input[name*="name"], .fi-fo-field-wrp:has(input)', 'title' => 'Keep identification dependable', 'body' => 'Use a clear warehouse-friendly name and a real barcode or UPC when available. The scanner depends on these fields to find the correct item quickly.'],
                    ['el' => '.fi-section:has(input[name*="is_container"]), .vx-choice-cards', 'title' => 'Container means this SKU holds other SKUs', 'body' => 'Choose container for a case, box, or pack that contains individual inventory items. Choose single item when this product is tracked on its own. A container can exist before all of its inside items have been mapped.'],
                    ['el' => '.fi-section:has([wire\\:model*="childContents"]), .fi-fo-repeater', 'title' => 'Define what is inside only when known', 'body' => 'For a mapped case, add each child item and the quantity inside one container. This lets a case barcode expand into the correct individual inventory later. Do not invent contents just to complete the form.'],
                    ['el' => '.fi-page-header-actions, .fi-header-actions', 'title' => 'Stock has its own workflow', 'body' => 'Use Move / Correct Stock when the count or location changes. Keeping quantity changes out of Edit preserves inventory history and makes discrepancies easier to trace.'],
                ],
            ],

            'inventory-stock' => [
                'title' => 'Move or Correct Stock',
                'steps' => [
                    ['el' => '[data-tour="stock-purpose"]', 'title' => 'Choose based on what physically happened', 'body' => 'Correct / remove changes the count at one location. Move stock keeps the total quantity the same and transfers units between locations.'],
                    ['el' => '[data-tour="stock-current"]', 'title' => 'Start with where the units are now', 'body' => 'Tap the location you are changing. The number beside it is the quantity currently recorded there.'],
                    ['el' => '[data-tour="stock-operation"]', 'title' => 'Correction and transfer are different events', 'body' => 'Use a correction for a count discrepancy, loss, giveaway, promo, or internal use. Use Move stock when the units still exist but were physically moved somewhere else.'],
                    ['el' => '[data-tour="stock-form"]', 'title' => 'For corrections, enter what remains', 'body' => 'The correction field asks for the actual quantity now. If 10 are recorded and 2 were given away, enter 8. For transfers, enter only the number being moved.'],
                    ['el' => '[data-tour="stock-preview"]', 'title' => 'Read this before saving', 'body' => 'The preview spells out what will change and what each location will contain afterwards. If that sentence does not match what happened physically, change the form before saving.'],
                ],
            ],

            'inventory-scanner' => [
                'title' => 'Quick Scan',
                'steps' => [
                    ['title' => 'Scan something in your hand', 'body' => 'Use Lookup when you want to identify an item or see where it is. Use Quick Add when you are intentionally booking stock into a location.'],
                    ['el' => '#camera-scan-btn, #camera-scan-btn-mobile, [data-camera-scan]', 'title' => 'Use the rear camera', 'body' => 'Tap Camera and point the phone at the UPC or barcode. The scanner confirms the same code several times before accepting it to reduce false reads. Manual entry is always available as a fallback.'],
                    ['el' => 'input[id*="barcode"], input[placeholder*="barcode" i], input[placeholder*="scan" i]', 'title' => 'Scanner guns work too', 'body' => 'A USB or Bluetooth scanner can type directly into the barcode field. Lookup automatically runs when a complete code arrives; Quick Add stays explicit so a partial scan cannot change stock.'],
                    ['el' => '.fi-main, main', 'title' => 'For shipments, use Receive Inventory', 'body' => 'Quick Scan is for one item at a time. A vendor pallet should be received from its pallet screen so expected quantities, landed cost, shortages, photos, and receiving history stay together.'],
                ],
            ],

            'inventory-quick-add' => [
                'title' => 'Quick Add',
                'steps' => [
                    ['title' => 'Keep it quick', 'body' => 'Use this when you need a simple inventory record now. Start with the product name and barcode; details such as notes, vendor settings, and reorder information can be filled in later.'],
                    ['el' => 'input[wire\\:model="data.name"]', 'title' => 'Use a name people will actually search', 'body' => 'Name the product the way someone in the warehouse would say it. Clear names make receiving and show reporting much faster later.'],
                    ['el' => '#quickadd-scan-btn', 'title' => 'Scan instead of typing a UPC', 'body' => 'Tap Scan Barcode to open the camera. If the barcode already belongs to another product, use that existing product rather than making another copy.'],
                    ['el' => 'input[wire\\:model="data.sku"]', 'title' => 'SKU is optional', 'body' => 'Leave SKU blank when you do not have one. A barcode or the product name can still identify the item.'],
                ],
            ],

            'inventory-locations' => [
                'title' => 'Inventory Locations',
                'steps' => [
                    ['title' => 'Locations are where stock actually sits', 'body' => 'Every quantity belongs to a location. Main storage, streamer inventory, receiving, damaged stock, and other areas should be represented accurately because transfers use these locations.'],
                    ['el' => '.fi-ta-header-toolbar .fi-btn, .fi-page-header-actions .fi-btn', 'title' => 'Use the correct location type', 'body' => 'Main Storage is the normal shared stock location. Streamer Inventory is intentionally scoped to a streamer, so do not use that type for a general shelf.'],
                    ['el' => '.fi-ta-table tbody tr:first-child, .fi-ta', 'title' => 'Move stock instead of editing history', 'body' => 'When inventory changes places, use the item transfer action. That preserves a movement record instead of silently changing a location.'],
                ],
            ],

            'pallet-list' => [
                'title' => 'Receive Inventory',
                'steps' => [
                    ['title' => 'One pallet is one vendor delivery workspace', 'body' => 'Create or open the delivery you are physically receiving. Keep its packing slip, expected lines, freight, photos, shortages, and received quantities together.'],
                    ['el' => '.fi-ta-search-field, .fi-ta', 'title' => 'Find the pallet, then work inside it', 'body' => 'Search by pallet name, vendor, or reference. Open the pallet instead of receiving its products independently so landed cost and receiving history remain accurate.'],
                    ['el' => '.fi-page-header-actions, .fi-ta-header-toolbar', 'title' => 'New delivery starts here', 'body' => 'Create a pallet when a shipment is expected or arrives. Add the manifest, then use its Receive screen while unloading it.'],
                ],
            ],

            'pallet-view' => [
                'title' => 'Pallet Workspace',
                'steps' => [
                    ['title' => 'Everything about this delivery stays together', 'body' => 'Expected products, paperwork, costs, photos, shortages, and receiving progress all belong to this pallet.'],
                    ['el' => '[data-tour="pallet-lines"], .fi-section:has(table)', 'title' => 'Manifest lines are what you expect', 'body' => 'One line should represent each product or case on the packing slip. A product does not have to exist in inventory before the pallet arrives.'],
                    ['el' => '[data-tour="pallet-scan"], .fi-page-header-actions', 'title' => 'Open Receive when the shipment is in front of you', 'body' => 'The Receive screen is designed for the warehouse floor. Scan each line, count a full line when appropriate, and record shortages without leaving the pallet.'],
                    ['el' => '[data-tour="pallet-costs"]', 'title' => 'Keep freight and fees with the delivery', 'body' => 'Shipping and payment fees belong on the pallet because they are part of landed inventory cost.'],
                ],
            ],

            'pallet-receive' => [
                'title' => 'Receiving Station',
                'steps' => [
                    ['el' => '[data-tour="receiving-summary"], main', 'title' => 'Work from expected to received', 'body' => 'The progress count is the main signal: how many boxes were expected, how many are in, and what is still left to handle.'],
                    ['el' => '[data-tour="pallet-scan"], #camera-scan-btn-mobile, #camera-scan-btn', 'title' => 'Tap Scan on the item you are holding', 'body' => 'For an unmapped line, tapping Scan aims the next barcode at that exact line and opens the camera on a phone. The first successful scan can create/link the product and count the box in one flow.'],
                    ['el' => '[data-tour="manifest-lines"], .fi-main', 'title' => 'Use Receive All only after you counted the line', 'body' => 'Receive All is the fast path when every expected box for a mapped line is physically present. Otherwise keep scanning so the progress remains honest.'],
                    ['el' => '[data-tour="receiving-complete"], .fi-main', 'title' => 'Record exceptions before completing', 'body' => 'Mark shortages when something did not arrive and add photos or paperwork if useful. Finalize only after the receiving summary matches what you actually received.'],
                ],
            ],

            'payouts' => [
                'title' => 'Payouts',
                'steps' => [
                    ['title' => 'What each worker is owed', 'body' => 'One row per payout. The configured pay type determines how the amount is calculated.'],
                    ['el' => '.fi-ta-filters-trigger, .fi-ta-actions', 'title' => 'Work one period at a time', 'body' => 'Filter to the period being paid and review the source figures before approval.'],
                    ['el' => '.fi-ta-table tbody tr:first-child', 'title' => 'Check before approving', 'body' => 'Open a payout to see what produced the amount. Approval is the point it becomes a real payment obligation.'],
                ],
            ],
        ];
    }

    public static function routeMap(): array
    {
        return [
            'filament.admin.resources.inventory-items.index' => 'inventory-list',
            'filament.admin.resources.inventory-items.quick-add' => 'inventory-quick-add',
            'filament.admin.resources.inventory-items.view' => 'inventory-item',
            'filament.admin.resources.inventory-items.edit' => 'inventory-edit',
            'filament.admin.resources.inventory-items.stock' => 'inventory-stock',
            'filament.admin.pages.inventory-scanner' => 'inventory-scanner',
            'filament.admin.resources.inventory-locations.index' => 'inventory-locations',
            'filament.admin.resources.pallets.index' => 'pallet-list',
            'filament.admin.resources.pallets.view' => 'pallet-view',
            'filament.admin.resources.pallets.receive' => 'pallet-receive',
            'filament.admin.resources.payouts.index' => 'payouts',
        ];
    }

    public static function forRoute(?string $routeName, ?object $user = null): ?array
    {
        $tour = static::tourFor(static::routeMap()[$routeName] ?? null);
        if ($tour === null) return null;

        $seen = $user?->completed_tours ?? [];
        return [...$tour, 'auto' => ! in_array($tour['id'], $seen, true)];
    }

    public static function tourFor(?string $id): ?array
    {
        if ($id === null) return null;
        $tour = static::definitions()[$id] ?? null;
        return $tour === null ? null : ['id' => $id, ...$tour];
    }

    public static function ids(): array
    {
        return array_keys(static::definitions());
    }
}
