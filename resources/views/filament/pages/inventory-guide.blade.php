<x-filament-panels::page>
@php($status = $this->locationStatus)
<div class="space-y-6">

{{-- ── Tab strip ────────────────────────────────────────────────────────────── --}}
<div class="flex rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-1 gap-1 overflow-x-auto">
    @foreach([
        'start'   => ['📍', 'Start Here'],
        'add'     => ['➕', 'Add an Item'],
        'restock' => ['📷', 'Restock & Scan'],
        'pallets' => ['🚚', 'Receiving'],
        'fix'     => ['🔀', 'Move & Correct'],
        'trouble' => ['🩺', 'Troubleshooting'],
    ] as $key => [$icon, $label])
    <button wire:click="setTab('{{ $key }}')" type="button"
        class="flex-shrink-0 flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition-colors whitespace-nowrap
            {{ $tab === $key ? 'bg-violet-600 text-white shadow' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
        <span>{{ $icon }}</span> {{ $label }}
    </button>
    @endforeach
</div>

{{-- ════════════════════════════════════════════════════════════════ START HERE --}}
@if($tab === 'start')
<div class="space-y-6">

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-5">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">Items and stock are two different things</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            An <strong class="text-gray-700 dark:text-gray-200">item</strong> is the thing itself — "2024 Topps Chrome Hobby Box".
            <strong class="text-gray-700 dark:text-gray-200">Stock</strong> is how many you have and where they sit.
            You create the item once; you add stock to it forever after. Nearly every inventory mistake is a second copy
            of an item that already existed.
        </p>
    </div>

    {{-- Live check of this install, rather than telling the reader to go look. --}}
    @if($status['count'] === 0)
        <div class="rounded-xl border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 px-6 py-5">
            <h3 class="text-sm font-semibold text-amber-900 dark:text-amber-200 mb-1">⚠ You have no active locations</h3>
            <p class="text-sm text-amber-800 dark:text-amber-300">
                Stock has nowhere to go, which is why location dropdowns look empty. Create one before anything else —
                the steps are below.
            </p>
        </div>
    @elseif(! $status['hasStorage'])
        <div class="rounded-xl border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 px-6 py-5">
            <h3 class="text-sm font-semibold text-amber-900 dark:text-amber-200 mb-1">⚠ No general storage location</h3>
            <p class="text-sm text-amber-800 dark:text-amber-300">
                You have {{ $status['count'] }} active {{ Str::plural('location', $status['count']) }}
                ({{ implode(', ', array_slice($status['names'], 0, 6)) }}@if($status['count'] > 6), …@endif),
                but none of type <strong>Main Storage</strong>. If you have been looking for "the warehouse" — this is what it is called,
                and it does not exist yet.
            </p>
        </div>
    @else
        <div class="rounded-xl border border-green-300 dark:border-green-800 bg-green-50 dark:bg-green-950/40 px-6 py-5">
            <h3 class="text-sm font-semibold text-green-900 dark:text-green-200 mb-1">✓ Locations are set up</h3>
            <p class="text-sm text-green-800 dark:text-green-300">
                {{ $status['count'] }} active {{ Str::plural('location', $status['count']) }}, including general storage.
                Nothing to do here.
            </p>
        </div>
    @endif

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-5">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Creating a location</h3>
        <ol class="space-y-3 text-sm text-gray-600 dark:text-gray-300">
            @foreach([
                ['Open Inventory → Locations.', 'Admin role required. If it is missing from your sidebar, ask the owner to grant it rather than working around it.'],
                ['Click New, name it Main Storage, set Type to Main Storage.', 'There is no type called "warehouse" — this is the general-purpose one.'],
                ['Leave the status Active.', 'Inactive locations are hidden from every dropdown, which is the other reason a list looks empty.'],
                ['Add your other real places, one row each.', 'Types available: Main Storage, Streamer Inventory, Returned, Damaged, Fulfillment, Other.'],
            ] as $i => [$title, $body])
            <li class="flex gap-3">
                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-violet-100 dark:bg-violet-900/50 text-violet-700 dark:text-violet-300 text-xs font-bold grid place-items-center">{{ $i + 1 }}</span>
                <span>
                    <strong class="text-gray-900 dark:text-gray-100">{{ $title }}</strong>
                    <span class="block text-gray-500 dark:text-gray-400">{{ $body }}</span>
                </span>
            </li>
            @endforeach
        </ol>
    </div>

    <div class="rounded-xl border border-violet-200 dark:border-violet-800 bg-violet-50 dark:bg-violet-950/40 px-6 py-5">
        <h3 class="text-sm font-semibold text-violet-900 dark:text-violet-200 mb-1">Type is not decoration</h3>
        <p class="text-sm text-violet-800 dark:text-violet-300">
            <strong>Streamer Inventory</strong> locations are filtered per streamer — a streamer signed in sees only their own.
            Naming a shared shelf as streamer inventory hides it from everybody else.
        </p>
    </div>

</div>
@endif

{{-- ══════════════════════════════════════════════════════════════ ADD AN ITEM --}}
@if($tab === 'add')
<div class="space-y-6">

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @foreach([
            ['Quick Add', 'Fast path', 'Three steps: details, optional opening stock, review. Use it when you are holding something and want it in the system now.'],
            ['Create Inventory Item', 'Full path', 'Four steps, with settings and extras. Use it when setting a product up properly — vendor, reorder points, notes.'],
        ] as [$name, $when, $body])
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-violet-600 dark:text-violet-400 mb-1">{{ $when }}</p>
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">{{ $name }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $body }}</p>
        </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-5">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Both start from Inventory → All Inventory</h3>
        <ol class="space-y-3 text-sm text-gray-600 dark:text-gray-300">
            @foreach([
                ['Name it.', 'The only required field. Everything else can wait, and everything else can be edited later.'],
                ['Scan or type the barcode.', 'Press 📷 Scan and the camera opens. A USB scanner gun also works — click into the field and scan, it types for you.'],
                ['Leave SKU blank if you do not have one.', 'It is optional, and blank is fine on any number of items.'],
                ['Add opening stock, or do not.', 'Pick the location and quantity if some is already on the shelf. Leave Stock Unit Cost empty to use the item\'s own cost — that is the normal case.'],
                ['Review and save.', 'You land on the item page with the stock already recorded.'],
            ] as $i => [$title, $body])
            <li class="flex gap-3">
                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-violet-100 dark:bg-violet-900/50 text-violet-700 dark:text-violet-300 text-xs font-bold grid place-items-center">{{ $i + 1 }}</span>
                <span>
                    <strong class="text-gray-900 dark:text-gray-100">{{ $title }}</strong>
                    <span class="block text-gray-500 dark:text-gray-400">{{ $body }}</span>
                </span>
            </li>
            @endforeach
        </ol>
    </div>

    <div class="rounded-xl border border-green-300 dark:border-green-800 bg-green-50 dark:bg-green-950/40 px-6 py-5">
        <h3 class="text-sm font-semibold text-green-900 dark:text-green-200 mb-1">If the barcode is already taken</h3>
        <p class="text-sm text-green-800 dark:text-green-300">
            You will be told it is on another item. That is not an obstacle to work around — it means the item already exists.
            Search it in All Inventory and add stock to it instead of creating a duplicate.
        </p>
    </div>

</div>
@endif

{{-- ═════════════════════════════════════════════════════════ RESTOCK & SCAN --}}
@if($tab === 'restock')
<div class="space-y-6">

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-5">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">The everyday job</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Adding more of something you already stock is <em>not</em> the same screen as adding a new item.
            Use <strong class="text-gray-700 dark:text-gray-200">Quick Add Stock</strong>.
        </p>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-5">
        <ol class="space-y-3 text-sm text-gray-600 dark:text-gray-300">
            @foreach([
                ['Open Quick Add Stock.', 'From the Inventory menu.'],
                ['Scan the barcode.', 'The matching item appears. If nothing appears, that barcode is not on file — go add the item first.'],
                ['Set quantity, location, and cost.', 'Enter a unit cost only if this batch cost something different from the last one.'],
                ['Add it.', 'Recent additions stay on screen so you can confirm the last few scans without leaving the page.'],
            ] as $i => [$title, $body])
            <li class="flex gap-3">
                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-violet-100 dark:bg-violet-900/50 text-violet-700 dark:text-violet-300 text-xs font-bold grid place-items-center">{{ $i + 1 }}</span>
                <span>
                    <strong class="text-gray-900 dark:text-gray-100">{{ $title }}</strong>
                    <span class="block text-gray-500 dark:text-gray-400">{{ $body }}</span>
                </span>
            </li>
            @endforeach
        </ol>
    </div>

    <div class="rounded-xl border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 px-6 py-5">
        <h3 class="text-sm font-semibold text-amber-900 dark:text-amber-200 mb-1">Cost affects more than this batch</h3>
        <p class="text-sm text-amber-800 dark:text-amber-300">
            Every receipt recalculates the item's weighted average cost across everything on hand. A wrong cost does not
            just mis-price this batch — it shifts the value of stock you already had.
        </p>
    </div>

</div>
@endif

{{-- ═══════════════════════════════════════════════════════════════ RECEIVING --}}
@if($tab === 'pallets')
<div class="space-y-6">

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-5">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">For deliveries, not handfuls</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Receiving keeps the vendor, PO and freight cost attached to the stock, which is what makes landed cost correct.
            Adding a pallet's worth of boxes one at a time through Quick Add loses all of that.
        </p>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-5">
        <ol class="space-y-3 text-sm text-gray-600 dark:text-gray-300">
            @foreach([
                ['Purchasing → Pallets → New.', 'Set the vendor and PO number.'],
                ['Add a line per product.', 'What it is, cases or units expected, and unit cost. Lines can be typed by name — the item does not need to exist yet.'],
                ['Map each line to a real item.', 'This connects "Topps Chrome" on a packing slip to the actual product record.'],
                ['Receive it.', 'Scan each case as you unload, or press Receive All on a line you counted by hand.'],
                ['Mark the pallet received.', 'Stock lands in each line\'s location, or the default receiving location.'],
            ] as $i => [$title, $body])
            <li class="flex gap-3">
                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-violet-100 dark:bg-violet-900/50 text-violet-700 dark:text-violet-300 text-xs font-bold grid place-items-center">{{ $i + 1 }}</span>
                <span>
                    <strong class="text-gray-900 dark:text-gray-100">{{ $title }}</strong>
                    <span class="block text-gray-500 dark:text-gray-400">{{ $body }}</span>
                </span>
            </li>
            @endforeach
        </ol>
        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
            There is a deeper walkthrough of matching and scanner modes under <strong class="text-gray-700 dark:text-gray-200">Purchasing → How It Works</strong>.
        </p>
    </div>

</div>
@endif

{{-- ═════════════════════════════════════════════════════════ MOVE & CORRECT --}}
@if($tab === 'fix')
<div class="space-y-6">

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @foreach([
            ['It moved', 'Stock Transfer', 'Pick the item, where it left, where it arrived, and how much. Total stock does not change — only where it sits.'],
            ['The count was wrong', 'Inventory Reconciliation', 'Enter what is actually on the shelf. The difference is recorded as an adjustment, with who changed it and when.'],
        ] as [$when, $screen, $body])
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-violet-600 dark:text-violet-400 mb-1">{{ $when }}</p>
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">{{ $screen }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $body }}</p>
        </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 px-6 py-5">
        <h3 class="text-sm font-semibold text-amber-900 dark:text-amber-200 mb-1">Do not fix a count by transferring</h3>
        <p class="text-sm text-amber-800 dark:text-amber-300">
            Moving stock you cannot find into a "Damaged" location makes the totals look right while hiding a real
            discrepancy. Reconcile instead — the adjustment <em>is</em> the record that something went missing, and that
            record is the whole point.
        </p>
    </div>

</div>
@endif

{{-- ═════════════════════════════════════════════════════════ TROUBLESHOOTING --}}
@if($tab === 'trouble')
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
    @foreach([
        ['The stock location dropdown is empty',
         'No active locations exist, or the ones that do are marked inactive. See the Start Here tab.'],
        ['I cannot find a main warehouse anywhere',
         'The general-purpose location is called Main Storage, not "warehouse". If it is genuinely absent, create it — do not repurpose a streamer location, since those are filtered per streamer and a shared shelf would vanish for everyone else.'],
        ['That barcode is already on another item',
         'Working as intended. Barcodes are unique so a scan resolves to exactly one product. Search the barcode in All Inventory to find the item that owns it.'],
        ['The camera button does nothing',
         'Camera access is per-site and per-browser. Open the padlock beside the address bar, allow the camera, then reload. On a machine with no camera, use a scanner gun into the barcode field — it behaves like a keyboard.'],
        ['Stock is right but the value looks wrong',
         'Check the unit cost on the receipts, not on the item. Average cost is recalculated from every receipt, so one batch entered at $0.00 drags down the value of everything on hand.'],
        ['Quick Add threw an Internal Server Error',
         'A bug on the final step discarded everything on submit — which is why scanning appeared not to work: nothing saved, rather than the scan failing. Fixed. If you still see it, this server has not been updated yet.'],
    ] as [$symptom, $answer])
    <div class="px-6 py-4">
        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-1">“{{ $symptom }}”</p>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $answer }}</p>
    </div>
    @endforeach
</div>
@endif

</div>
</x-filament-panels::page>
