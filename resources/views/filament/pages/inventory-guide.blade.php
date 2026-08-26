<x-filament-panels::page>
@php($status = $this->locationStatus)
<div class="space-y-6">

{{-- ── Tab strip ────────────────────────────────────────────────────────────── --}}
<div class="flex rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-1 gap-1 overflow-x-auto">
    @foreach($this->visibleTabs as $key => [$icon, $label])
    <button wire:click="setTab('{{ $key }}')" type="button"
        class="flex-shrink-0 flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition-colors whitespace-nowrap
            {{ $tab === $key ? 'bg-violet-600 text-white shadow' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
        <span>{{ $icon }}</span> {{ $label }}
    </button>
    @endforeach
</div>

{{-- Setup state, above the tabs on purpose. With no location nothing on any
     tab can be followed to the end, so this is not one tab's business. --}}
@if($status['count'] === 0)
    <x-guide.panel tone="amber" title="⚠ You have no active locations">
        <p>Stock has nowhere to go, which is exactly why location dropdowns elsewhere look empty. Create one before anything else.</p>
    </x-guide.panel>
@elseif(! $status['hasStorage'])
    <x-guide.panel tone="amber" title="⚠ No general storage location">
        <p>
            You have {{ $status['count'] }} active {{ Str::plural('location', $status['count']) }}
            ({{ implode(', ', array_slice($status['names'], 0, 6)) }}@if($status['count'] > 6), …@endif),
            and none of type <strong>Main Storage</strong>. If you have been hunting for "the warehouse",
            that is its name here — and it does not exist yet.
        </p>
    </x-guide.panel>
@else
    <x-guide.panel tone="green" title="✓ Locations are set up">
        <p>{{ $status['count'] }} active {{ Str::plural('location', $status['count']) }}, including general storage. Nothing to do on this tab.</p>
    </x-guide.panel>
@endif

{{-- ═══════════════════════════════════════════════════════════════ FULL CYCLE --}}
@if($tab === 'cycle')
<div class="space-y-5">

    {{-- The printed handbook covers the same ground in more detail and can be
         carried to the receiving bay, where the person doing the job is. --}}
    <div class="rounded-xl border border-violet-200 bg-violet-50 px-5 py-4 dark:border-violet-800 dark:bg-violet-950/40">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h3 class="text-sm font-semibold text-violet-900 dark:text-violet-200">Prefer it on paper?</h3>
                <p class="mt-0.5 text-xs text-violet-800 dark:text-violet-300">
                    The full Inventory Handbook — adding and editing items, transfers, staging and receiving
                    pallets, bulk receive, and scanning UPCs onto items after the fact — as a PDF with a
                    screenshot for every step.
                </p>
            </div>
            <a href="{{ route('export.inventory-manual-pdf', ['download' => 1]) }}"
               style="display:inline-flex !important"
               class="min-h-10 shrink-0 items-center gap-1.5 rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-700">
                <x-heroicon-o-arrow-down-tray class="h-4 w-4" /> Download PDF
            </a>
        </div>
    </div>

    <x-guide.panel tone="violet" title="One box, from the loading dock to the payout">
        <p>Every other tab in this guide explains a screen. This one follows a single box
        through all of them, in order, so you can see where each screen sits in the run of
        things. Work down it once and the rest of the guide stops being a list of pages.</p>
        <p class="mt-2">The screenshots are of this install, not a demo — what you see here
        is what you will see when you click through.</p>
    </x-guide.panel>

    <x-guide.walkthrough
        number="1"
        title="Have somewhere to put it"
        screen="Inventory → Locations"
        url="{{ \App\Filament\Resources\InventoryLocationResource::getUrl('index') }}"
        shot="01-locations.png">
        <p>Stock is always <em>somewhere</em>. A location is that somewhere — a shelf, a
        streamer's setup, the returns bin.</p>
        <p>Get this right before anything else: every later step asks which location, and a
        receipt booked to the wrong one is a count that will not reconcile weeks later.</p>
        <p class="text-gray-500 dark:text-gray-400">You only do this once, when setting up
        or when a new shelf appears.</p>
    </x-guide.walkthrough>

    <x-guide.walkthrough
        number="2"
        title="Make sure the item exists in the catalogue"
        screen="Inventory → All Inventory"
        url="{{ \App\Filament\Resources\InventoryItemResource::getUrl('index') }}"
        shot="02-item-list.png">
        <p>Search the code on the box first. If it is already here, skip to step 4 — creating
        a second record for something you already stock is the single commonest way a count
        goes wrong.</p>
        <p>Nothing found? Two routes: <strong>Add Item</strong> for the full form, or
        <strong>Quick Add</strong> when you have a box in one hand and want a name, a cost
        and a code recorded in twenty seconds.</p>
        <p>If the item is there but has no barcode, open its row menu and choose
        <strong>Scan Barcode</strong> — scan, and the code is saved to it without opening
        the item at all.</p>
    </x-guide.walkthrough>

    <x-guide.walkthrough
        number="3"
        title="Record what it costs and what it should fetch"
        screen="Inventory → All Inventory → Add Item"
        url="{{ \App\Filament\Resources\InventoryItemResource::getUrl('create') }}"
        shot="03-item-create.png">
        <p>Two numbers matter and they are not the same. <strong>List Unit Cost</strong> is
        the fallback price used until real receipts exist. <strong>Sale Price / Target</strong>
        is what it is meant to sell for.</p>
        <p>The margin underneath answers as you type: target minus whichever cost is real for
        this item. It is not stored anywhere — it is recalculated from the weighted average
        every time you look, so it never drifts out of date after a receipt.</p>
        <p class="text-gray-500 dark:text-gray-400">Leaving the target blank is fine; the
        item simply drops out of every margin figure until it has one, and the
        <em>Missing a sale target</em> filter will find it again.</p>
    </x-guide.walkthrough>

    <x-guide.walkthrough
        number="4"
        title="Book the delivery in against a pallet"
        screen="Inventory → Pallets"
        url="{{ \App\Filament\Resources\PalletResource::getUrl('index') }}"
        shot="04-pallets.png">
        <p>A pallet is one delivery from one vendor. Create it, add a line per product with
        what the paperwork says you should be getting, then receive against it.</p>
        <p>Receiving is where cost actually enters the system: each receipt recalculates the
        item's weighted average, so the cost of everything downstream follows what you really
        paid rather than what the catalogue guessed.</p>
        <p><strong>You do not need a manifest.</strong> Start a blank pallet and scan what
        turned up; lines are created as you go.</p>
    </x-guide.walkthrough>

    <x-guide.walkthrough
        number="5"
        title="Scan it in"
        screen="Inventory → Quick Scan"
        url="{{ \App\Filament\Pages\InventoryScanner::getUrl() }}"
        shot="05-scanner.png">
        <p>Three modes, and the mode decides what a scan does. <strong>Look Up</strong> is
        read-only. <strong>Add Stock</strong> puts units into a location.
        <strong>Receive</strong> works a delivery off a pallet.</p>
        <p>Either input works: a USB or Bluetooth gun typed straight into the box, or the
        camera on a phone. Both end in the same place.</p>
        <p class="text-gray-500 dark:text-gray-400">Received everything on a line without
        counting each one? Use <em>Receive all</em>. Short? Say how many turned up and mark
        the rest short — the discrepancy is recorded rather than argued about later.</p>
    </x-guide.walkthrough>

    <x-guide.walkthrough
        number="6"
        title="Check it landed where you meant"
        screen="Inventory → Stock Levels"
        url="{{ \App\Filament\Resources\InventoryStockResource::getUrl('index') }}"
        shot="06-stock.png">
        <p>One row per item per location — the answer to "how many, and where?".</p>
        <p>This is the screen to look at straight after a receipt. If a number is not where
        you expect, it is nearly always a location picked in haste at step 4.</p>
        <p>Moving stock between shelves is <strong>Stock Transfer</strong>, never editing a
        number by hand: a transfer records both sides, an edit records nothing.</p>
    </x-guide.walkthrough>

    <x-guide.walkthrough
        number="7"
        title="Send it out, and account for what left"
        screen="Streams → End of Stream"
        url="{{ \App\Filament\Pages\EndOfStreamForm::getUrl() }}"
        shot="07-movements.png">
        <p>Stock leaves when a streamer files their End of Stream report. They scan or pick
        what they used, and the approved report is what deducts it — inventory is not touched
        by Whatnot sales directly, because giveaways and promos leave the shelf too and have
        no order behind them.</p>
        <p>Every change lands in <strong>Movement History</strong>: what moved, when, who did
        it, and why. Nothing in the system changes a quantity without writing a row here.</p>
    </x-guide.walkthrough>

    <x-guide.walkthrough
        number="8"
        title="See what it was worth"
        screen="Inventory → Inventory Value"
        url="{{ \App\Filament\Pages\InventoryValueDashboard::getUrl() }}"
        shot="08-value.png">
        <p>What is on the shelf, at weighted average cost, and what margin is sitting in it
        at your sale targets.</p>
        <p>This is the number the whole cycle exists to keep honest — and it is only as good
        as steps 4 and 7. Cost enters at receiving; it leaves at the report.</p>
    </x-guide.walkthrough>

    <x-guide.panel tone="amber" title="If a count is wrong, do not just fix the number">
        <p>Editing a quantity to make it match tells you nothing next month. Use
        <strong>Inventory Reconciliation</strong> to record a physical count, or
        <strong>Adjust</strong> on the item with a reason. Both leave a row in Movement
        History saying what happened and who said so, which is what turns a recurring
        discrepancy into something you can actually track down.</p>
    </x-guide.panel>

</div>
@endif

{{-- ════════════════════════════════════════════════════════════════ START HERE --}}
@if($tab === 'start')
<div class="space-y-6">

    <x-guide.panel title="Three things, and they are not the same thing">
        <p class="mb-3">Almost every confusion in inventory comes from these being blurred together.</p>
        <ul class="space-y-1.5">
            <li><strong class="text-gray-900 dark:text-gray-100">Item</strong> — the product itself. "2025 Topps Chrome Hobby Box". Created once, ever.</li>
            <li><strong class="text-gray-900 dark:text-gray-100">Location</strong> — a place stock can sit. A shelf, a streamer's shelf, the damaged pile.</li>
            <li><strong class="text-gray-900 dark:text-gray-100">Stock</strong> — how many of an item are at a location. This is the number that moves.</li>
        </ul>
        <p class="mt-3">
            You create an item once and add stock to it forever after. A second item for something you already
            stock splits your counts and your costs in two, and nothing warns you it happened.
        </p>
    </x-guide.panel>

    @if($this->canSee(\App\Filament\Resources\InventoryLocationResource::class))
    <x-guide.steps title="Locations — Inventory → Locations" :steps="[
        ['Open it', 'Admin only. If it is missing from your sidebar that is a role, not a bug.'],
        ['New → name it Main Storage → type Main Storage', 'There is no type called warehouse. Main Storage is the general-purpose one.'],
        ['Leave the status Active', 'Inactive locations vanish from every dropdown in the app — the second reason a list looks empty, and it looks identical to the first.'],
        ['Add your other real places', 'One row per physical place. Resist inventing locations that are really statuses; Damaged and Returned exist as types already.'],
    ]" />

    @endif

    <x-guide.table title="The six location types" :rows="[
        ['Main Storage', 'General stock. Where receiving lands unless told otherwise.'],
        ['Streamer Inventory', 'Assigned to one streamer, who sees only their own. Never use it for a shared shelf.'],
        ['Fulfillment', 'Picked and waiting to ship. Separating it stops packed orders counting as sellable.'],
        ['Returned', 'Back from a buyer, or going back to a vendor. Out of sellable stock, still tracked.'],
        ['Damaged', 'Unsellable. Visible, so damage reads as damage rather than as stock quietly disappearing.'],
        ['Other', 'Genuinely none of the above. Reaching for it often means a type is missing.'],
    ]" />

    <x-guide.table title="Also under setup" :rows="array_values(array_filter([
        $this->canSee(\App\Filament\Resources\VendorResource::class)
            ? ['Vendors', 'Who you buy from. A vendor is required to create a pallet, so set these up before your first delivery.'] : null,
        $this->canSee(\App\Filament\Pages\BarcodePrinter::class)
            ? ['Barcode Printer', 'Generates printable barcode labels for items. Useful for anything that arrives without a scannable code.'] : null,
        ['Default receiving location', 'Under Settings. Stops receiving asking where stock goes on every line. With one active location, that one is used automatically.'],
]))" />

</div>
@endif

{{-- ════════════════════════════════════════════════════ ADDING & EDITING ITEMS --}}
@if($tab === 'items')
<div class="space-y-6">

    <x-guide.panel title="Search before you create. Every time.">
        <p>
            Duplicate items are the most expensive mistake here and the hardest to unpick: two rows, two stock
            counts, two averaged costs, and reports that quietly understate both. Search the name, the SKU and
            the barcode before adding anything.
        </p>
    </x-guide.panel>

    <x-guide.table title="All Inventory — the list everything starts from" :rows="[
        ['What it is', 'Every item you carry, one row each, with stock on hand.'],
        ['Search', 'Name, SKU or barcode. This is the duplicate check — do it first.'],
        ['Row actions', 'Add Stock, Move or correct stock, Mark Damaged, Move to Returns, Replace Barcode, Delete — plus Contents and Break Case on containers. The everyday operations without leaving the list.'],
        ['Advanced Search', 'A separate page for filter combinations you run repeatedly — it saves search profiles, which the list filters do not.'],
    ]" />

    <x-guide.steps title="Quick Add — the fast path" :steps="[
        ['Step 1 · Name it', 'The only required field. Say it the way you would out loud; this is what everyone searches for later.'],
        ['Step 1 · Scan or type the barcode', 'Press 📷 Scan for the camera, or click the field and use a scanner gun. Refused as already taken means the item exists — go find it.'],
        ['Step 1 · SKU, category, cost, vendor', 'All optional. SKU may be left blank on any number of items.'],
        ['Step 2 · Opening stock', 'Only if some is already on the shelf. Pick the location and quantity; leave Stock Unit Cost blank to use the item\'s own cost.'],
        ['Step 3 · Review and save', 'You land on the item page with the stock already recorded.'],
    ]" />

    <x-guide.steps title="Create Inventory Item — the full form, section by section" :steps="[
        ['Item Identification', 'Photo, name, Active switch, SKU with its regenerate button, barcode with its scan button.'],
        ['Container / Case Settings', 'Whether this is a container, and one row per thing inside it. Only mark it a container if you will break it into its parts.'],
        ['Classification & Sourcing', 'Category, preferred vendor, and Sold as — Auction, Buy It Now or Both, which is filterable and shows as a column.'],
        ['Pricing & Inventory Levels', 'List unit cost, average cost, sale target and reorder level. Margin Potential answers under the sale target as you type.'],
        ['Notes & Description', 'Description shows in lists; notes stay internal.'],
        ['Initial Stock', 'Create-only shortcut: opening quantity, location and cost in one step instead of creating the item and then adding stock.'],
    ]" />

    <x-guide.steps title="Editing an item" :steps="[
        ['Open it from All Inventory', 'The pencil icon on the row, or the item name.'],
        ['Change what the product is', 'Name, category, vendor, photo, reorder level, unit type — all safe to change any time.'],
        ['Barcode and SKU stay unique', 'Refused means another item owns that code. That is the duplicate you were about to make.'],
        ['Unit cost seeds future receipts', 'It does not rewrite the value of stock already on the shelf. Average cost is calculated, never typed.'],
        ['Retire rather than delete', 'Turn Active off when you stop carrying something. Deleting takes its movement history with it.'],
    ]" />

    <x-guide.panel tone="amber" title="Stock is not edited on the item form">
        <p>
            There is no quantity box, deliberately. Stock changes through receiving, Quick Add Stock, transfers and
            reconciliation — each of which records what changed, why, and who did it. A directly editable number
            would carry no reason, and the reason is the part you need when the count is questioned weeks later.
        </p>
    </x-guide.panel>

    <x-guide.steps title="Import Sheet — two hundred at once" :steps="[
        ['Choose the file', 'An .xlsx, .xls or .csv with a PRODUCT NAME column. SKU, Type, Cost, Sale price / Target and Auction or BIN? are read when they are there.'],
        ['Read what it would do', 'Every row matched against the catalogue first — what would be created, what would be updated and which field changes from what, what already matches, and what needs a look.'],
        ['Then decide', 'Nothing is written until the button at the bottom. A formula in a price cell is skipped and said so, rather than read as $0.00.'],
        ['Re-import safely', 'Costs and targets already set are left alone unless you tick the overwrite box, so a second run fills blanks instead of undoing corrections.'],
    ]" />

    <x-guide.table title="Catalog housekeeping" :rows="[
        ['Duplicate Detector', 'Finds likely duplicates — same name, close SKU — so you can merge them before they split stock and sales across two entries. Worth running monthly.'],
        ['Catalog Intelligence', 'Overall health of the product catalog: what is incomplete, inconsistent or unused.'],
    ]" />

</div>
@endif

{{-- ═══════════════════════════════════════════════════════════ RESTOCK & SCAN --}}
@if($tab === 'restock')
<div class="space-y-6">

    <x-guide.panel title="Adding more of something you already carry">
        <p>
            A different screen from adding an item. Use <strong>Quick Add Stock</strong> when a few boxes arrive
            outside a delivery — a local pickup, a trade, something found on a shelf. For a whole shipment use
            receiving instead, so the freight lands on the cost.
        </p>
    </x-guide.panel>

    <x-guide.steps title="Quick Add Stock" :steps="[
        ['Scan or type the barcode', 'The item appears. If nothing appears, that code is not on file — add the item first.'],
        ['Choose the location', 'Where these are physically going. Not where they usually live.'],
        ['Enter the quantity', 'How many you are adding, not the new total. The difference between adding six and setting six.'],
        ['Unit cost only if it differs', 'Blank uses the item\'s own cost, which is right most of the time.'],
        ['Add, then glance at the recent list', 'Recent additions stay on screen — the cheapest way to catch a double scan.'],
    ]" />

    <x-guide.table title="The scanning screens" :rows="[
        ['Scan Inventory', 'The main scanner. Three modes: look up an item, quick-add stock, or receive against a pallet. Look-up runs automatically once a code settles; the other two stay explicit so a half-typed code cannot book stock.'],
        ['Container Scan', 'Scan a container or case SKU first, then the individual items inside it. For breaking a case down as you unpack it.'],
        ['Mobile Scanner', 'The same work laid out for a phone held one-handed at the shelf.'],
    ]" />

    <x-guide.table title="Two ways to scan" :rows="[
        ['Camera', 'Any 📷 button. Grant camera permission once, per browser. Works on a phone at the shelf and on a laptop with a webcam.'],
        ['Scanner gun', 'Behaves like a keyboard — click into the field and scan. Faster for a stack, and it never argues about focus or lighting.'],
    ]" />

    <x-guide.table title="What a scan does depends on where you are" :rows="[
        ['Receiving a pallet', 'Receives one case against its line and moves the received count up by one.'],
        ['Quick Add Stock', 'Finds the item so you can set quantity and location.'],
        ['Creating or editing an item', 'Fills the barcode field, so future scans resolve to this product.'],
        ['An unmapped pallet line', 'Ties that code to the item, after which the rest of the pallet scans straight through.'],
    ]" />

    <x-guide.panel tone="amber" title="If the camera button does nothing">
        <p>
            Camera access is granted per site and per browser. Open the padlock beside the address bar, allow the
            camera, then reload. A machine with no camera has nothing to open — use a scanner gun there.
        </p>
    </x-guide.panel>

</div>
@endif

{{-- ═══════════════════════════════════════════════════════ STAGE & RECEIVE --}}
@if($tab === 'pallets')
<div class="space-y-6">

    <x-guide.panel title="Staging and receiving are two halves">
        <p>
            <strong>Staging</strong> is saying what you expect, before or as the delivery lands.
            <strong>Receiving</strong> is confirming what actually turned up. Doing the first makes the second a
            matter of scanning rather than typing, and it is what lets a short delivery announce itself.
        </p>
    </x-guide.panel>

    <x-guide.steps title="Staging a pallet" :steps="[
        ['Purchasing → Pallets → New, or stage from the scanner', 'The scanner has a staging panel so you can do this at the bench with the paperwork in hand.'],
        ['Vendor and reference', 'Both required. The reference is your PO or invoice number — it is how you find this pallet again when a vendor calls.'],
        ['Upload the packing slip, or skip it', 'Attach it if you have it; otherwise the pallet is created straight away and you type the lines.'],
        ['Add the expected items by name', 'Description, case count, units per case, unit cost. Items do not need to exist in the catalog yet — staged names are matched to real items later.'],
        ['Enter shipping and fees on the pallet', 'Not in your head. They spread across the lines and are the gap between the invoice total and what a box actually cost you.'],
    ]" />

    <x-guide.steps title="Receiving a pallet" :steps="[
        ['Open the pallet in Scan Inventory, receive mode', 'Pending staged items are listed with what is still outstanding.'],
        ['Map any unmapped line', 'Point it at a real item, or create the item from the line. An unmapped line cannot be received.'],
        ['Scan each case as you unload', 'One scan confirms one case. Camera or scanner gun; the camera button sits beside the barcode box.'],
        ['Or Receive All on a line you counted', 'For a line you have already counted by hand rather than scanning one at a time.'],
        ['Watch the progress panel', 'Received against expected, per line. A partial delivery simply leaves the rest outstanding — stop and come back tomorrow.'],
        ['Review, then close', 'Closing credits stock to each line\'s location. If a line is short and not coming, close it short so the shortfall is recorded rather than the pallet staying open forever.'],
    ]" />

    <x-guide.table title="What the scanner refuses, and why" :rows="[
        ['A code not on this pallet', 'Refused rather than guessed. Scanning a box from a different delivery into this one would silently misattribute its cost.'],
        ['Scanning past the expected count', 'Refused. Eleven scans against ten expected is a double scan far more often than it is a free box.'],
        ['An unmapped line', 'Cannot be received until it points at an item — there is nothing to credit the stock to.'],
    ]" />

    <x-guide.table title="Fields people get wrong" :rows="[
        ['Case count × units per case', 'Two numbers, not one. Twelve cases of six is not seventy-two loose boxes when you come to scan.'],
        ['Unit cost', 'Per unit unless the line is genuinely sold by the case. Getting this backwards is the most common costing error.'],
        ['Shipping and fees', 'Pallet level, spread across lines by value. Entering them on a line as well counts them twice.'],
        ['Is container', 'Marks a line arriving as a case to be broken down later. It sets up the breakdown rather than doing it now.'],
    ]" />

    <x-guide.table title="After the pallet is closed" :rows="[
        ['Pallet Status Dashboard', 'Status, receiving history, cost adjustments and session logs in one place. Start here when asking what happened to a delivery.'],
        ['Pallet Receiving History', 'Every received pallet with items, costs and PDF reports — the record to send a vendor.'],
        ['Receiving Sessions', 'Each receiving session with a per-item breakdown, exportable as PDF. Useful when two people worked one pallet.'],
        ['Receiving Analytics', 'Speed and accuracy over time, with a scorecard of your top vendors. Where you notice one vendor is consistently short.'],
    ]" />

    <x-guide.panel tone="violet" title="Photograph it while you are standing there">
        <p>
            Attach photos of the pallet as it arrived, the packing slip, and any damage — from the pallet page, on
            the day. A dispute three weeks later is settled by what you photographed, and nobody has ever regretted
            the extra picture.
        </p>
    </x-guide.panel>

</div>
@endif

{{-- ═════════════════════════════════════════════════════════════ COSTS & VALUE --}}
@if($tab === 'costs')
<div class="space-y-6">

    <x-guide.panel title="What a box is worth">
        <p>
            Each receipt re-blends the item's average cost across everything on hand — new stock and old, weighted
            by quantity. A cost typed wrong does not just mis-price that delivery; it moves the value of stock you
            already had. It is the one number worth slowing down for.
        </p>
    </x-guide.panel>

    <x-guide.table title="Where cost comes from" :rows="[
        ['Line unit cost', 'What the vendor charged. The main input, and the one you can check against the invoice.'],
        ['Shipping and fees', 'Entered on the pallet, spread across its lines. This is why freight belongs on the pallet.'],
        ['Stock unit cost override', 'On Quick Add Stock, for a batch that cost something different. Blank means use the item\'s own cost.'],
        ['Item unit cost', 'The expected price. Seeds receipts that do not say otherwise; never rewrites existing stock.'],
    ]" />

    <x-guide.panel tone="violet" title="Stock is consumed oldest first">
        <p>
            When boxes are broken on stream, the oldest are used first, at the cost they were received at. That is
            what makes a show's profit reflect what those specific boxes cost rather than today's price — and why a
            receipt entered wrong keeps affecting shows long after it was entered.
        </p>
    </x-guide.panel>

    <x-guide.steps title="If the value looks wrong" :steps="[
        ['Check the receipts, not the item', 'Average cost is derived. The item\'s unit cost field is not what produced it.'],
        ['Look for a zero', 'A batch received at $0.00 drags the average down across everything on hand. The single most common cause.'],
        ['Check per-unit against per-case', 'A case cost entered as a unit cost inflates value by the case size, and the reverse deflates it.'],
        ['Confirm freight landed once', 'Shipping on the pallet and again on a line counts it twice.'],
        ['Read the Cost Adjustment History', 'A full audit trail of cost changes with who made them and what changed. It answers "this was right last week".'],
    ]" />

    <x-guide.table title="The reporting screens" :rows="[
        ['Inventory Value Dashboard', 'Live value, velocity trends and stock insights. The headline number and what is moving it.'],
        ['Inventory Report', 'The comprehensive one: value, health, velocity and coverage together. Use it for a period review rather than a daily glance.'],
        ['Analytics', 'Key metrics, health status and quick actions — the everyday overview.'],
        ['Inventory Age', 'How long stock has been sitting. Old stock is money on a shelf, and it rarely gets better with time.'],
        ['Product Insights', 'Which products make money and which are stuck — margin and sell-through from your actual sales.'],
        ['Stock Levels & Movements', 'Current stock per item and location, and the movement log behind every change.'],
    ]" />

</div>
@endif

{{-- ═════════════════════════════════════════════════════════ FIXING MISTAKES --}}
@if($tab === 'fix')
<div class="space-y-6">

    <x-guide.panel title="Pick the tool that matches the reason">
        <p>
            All of these end with the same number on the shelf. They differ in what they record about
            <em>why</em> — which is the part you will want back when the numbers are questioned.
        </p>
    </x-guide.panel>

    <x-guide.table title="Reason, and the screen for it" :rows="[
        ['It moved to another shelf', 'Stock Transfer. Out of one location, into another; totals unchanged. Supports bulk moves when you are reorganising.'],
        ['The count was simply wrong', 'Inventory Reconciliation. Count the shelf, enter what is actually there, and the difference is recorded as an adjustment with who and when.'],
        ['It arrived or became damaged', 'Mark Damaged, from the item row. Out of sellable stock and still visible, so damage reads as damage rather than shrinkage.'],
        ['It is going back to the vendor', 'Move to Returns, from the item row. Separates it from sellable stock while you wait on the credit.'],
        ['A pallet line never turned up', 'Close pallet short. Records the shortfall against that line instead of leaving the pallet open indefinitely.'],
        ['A case needs splitting into boxes', 'Break down the container, from the item. Converts parent stock to child without inventing or losing value.'],
        ['Something is missing and nobody knows why', 'Missing Item Reports. Logs it as a discrepancy to investigate rather than adjusting it away quietly.'],
    ]" />

    <x-guide.steps title="Reconciling a location" :steps="[
        ['Pick one location, not all of them', 'Counting everything at once produces a long list of small discrepancies nobody investigates. One shelf produces a short list somebody chases.'],
        ['Count physically first', 'Write the numbers down before opening the screen. Counting with the expected figure visible is how you count what you expected.'],
        ['Enter actual counts', 'What is there, not the difference. The system works out the adjustment.'],
        ['Give a reason', 'Future-you reading this in three months is the audience. "Recount after move" beats a blank field.'],
        ['Review the adjustments before saving', 'A large discrepancy is worth a second count before it becomes a record.'],
    ]" />

    <x-guide.panel tone="amber" title="Never transfer stock you cannot find">
        <p>
            Moving missing boxes into a Damaged location makes the totals look right and hides a real discrepancy.
            Reconcile instead. The adjustment <em>is</em> the record that something went missing, and that record is
            the entire point — a tidy number with no explanation is worse than an untidy one with a reason.
        </p>
    </x-guide.panel>

</div>
@endif

{{-- ═════════════════════════════════════════════════════════ TROUBLESHOOTING --}}
@if($tab === 'trouble')
<div class="space-y-6">

    <x-guide.qa :items="[
        ['The stock location dropdown is empty', 'No active locations exist, or the ones that do are inactive — inactive locations are hidden everywhere. See Start Here.'],
        ['I cannot find a main warehouse anywhere', 'The general location is called Main Storage, not warehouse. If it is genuinely absent, create it. Do not repurpose a streamer location: those are filtered per streamer, so a shared shelf would vanish for everyone else.'],
        ['That barcode is already on another item', 'Working as intended. Barcodes are unique so a scan resolves to exactly one product. Search it in All Inventory to find the item that owns it.'],
        ['The camera button does nothing', 'Camera access is per site and per browser. Open the padlock beside the address bar, allow the camera, reload. On a machine with no camera, use a scanner gun.'],
        ['The scanner refuses a barcode while receiving', 'That code is not on this pallet, or the line is already fully received. Both are deliberate — scanning a box from another delivery would misattribute its cost.'],
        ['A pallet will not close', 'Every line must be mapped to an item and either received or closed short. An unmapped line is the usual culprit.'],
        ['Stock is right but the value looks wrong', 'Check the receipts rather than the item. One batch received at $0.00 drags the average across everything on hand. See Costs & Value.'],
        ['I added an item twice by mistake', 'Move any stock off the duplicate with a transfer, then deactivate it rather than deleting — deleting takes its movement history with it. The Duplicate Detector finds these before they spread.'],
        ['A streamer cannot see stock I can see', 'Streamer Inventory locations are filtered to their owner. If they should see it, it belongs somewhere that is not a streamer location.'],
        ['Quick Add threw an Internal Server Error', 'A bug on the final step discarded everything on submit — which is why scanning appeared not to work: nothing saved, rather than the scan failing. Fixed; if you still see it, this server has not been updated.'],
    ]" />

</div>
@endif

</div>
</x-filament-panels::page>
