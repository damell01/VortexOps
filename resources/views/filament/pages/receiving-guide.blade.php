<x-filament-panels::page>
<div class="space-y-6">

{{-- ── Tab strip ────────────────────────────────────────────────────────────── --}}
<div class="flex rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-1 gap-1 overflow-x-auto">
    @foreach([
        'workflow'  => ['🗺', 'Workflow Overview'],
        'matching'  => ['🤖', 'AI Matching'],
        'scanner'   => ['📱', 'Scanner Modes'],
        'products'  => ['📦', 'Product Catalog'],
        'shows'     => ['🎬', 'Shows & Deductions'],
        'tips'      => ['💡', 'Tips & Tricks'],
    ] as $key => [$icon, $label])
    <button wire:click="setTab('{{ $key }}')" type="button"
        class="flex-shrink-0 flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition-colors whitespace-nowrap
            {{ $tab === $key ? 'bg-violet-600 text-white shadow' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
        <span>{{ $icon }}</span> {{ $label }}
    </button>
    @endforeach
</div>

{{-- ════════════════════════════════════════════════════════════════════════════ --}}
{{-- WORKFLOW OVERVIEW                                                           --}}
{{-- ════════════════════════════════════════════════════════════════════════════ --}}
@if($tab === 'workflow')
<div class="space-y-6">

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-5">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">How VortexOps Inventory Works</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            VortexOps uses a <strong class="text-gray-700 dark:text-gray-200">Product-first</strong> model — the same box
            is always the same product, no matter which distributor you bought it from or what SKU they printed on the invoice.
            Vendor SKUs, packing slip descriptions, and order numbers are all metadata that points back to the product.
        </p>
    </div>

    {{-- Flow diagram --}}
    <div class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-center">
        @foreach([
            ['heroicon-o-inbox-arrow-down', 'violet', 'Receiving Session', 'Start every shipment here. Enter vendor, PO, invoice number.'],
            ['heroicon-o-document-text', 'blue', 'Pallet / Lines', 'Each line is one SKU from the packing slip, with quantity and cost.'],
            ['heroicon-o-cpu-chip', 'indigo', 'AI Matching', '3-stage pipeline maps vendor descriptions to your product catalog.'],
            ['heroicon-o-eye', 'amber', 'Review', 'You confirm fuzzy matches and create products for anything new.'],
            ['heroicon-o-archive-box', 'green', 'Inventory Lots', 'Stock is credited with a cost basis — ready for FIFO deductions.'],
        ] as [$icon, $color, $title, $body])
        <div class="rounded-xl border border-{{ $color }}-200 dark:border-{{ $color }}-800 bg-{{ $color }}-50 dark:bg-{{ $color }}-950 px-4 py-4 text-center">
            <x-dynamic-component :component="$icon" class="h-7 w-7 mx-auto text-{{ $color }}-500 mb-2" />
            <p class="text-sm font-semibold text-{{ $color }}-900 dark:text-{{ $color }}-100">{{ $title }}</p>
            <p class="text-xs text-{{ $color }}-700 dark:text-{{ $color }}-300 mt-1 leading-relaxed">{{ $body }}</p>
        </div>
        @endforeach
    </div>

    {{-- Key concepts --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4 space-y-2">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">📦 Product</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                The canonical record for a product — <em>2025 Topps Chrome Baseball Hobby Box</em>. Has brand, sport, year,
                set name, UPC, and configuration. Exists once. Stock aggregates here across all lots and locations.
            </p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4 space-y-2">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">🏷 Product Identity</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Every alias, UPC, barcode, or vendor SKU that refers to a product. When you confirm a match,
                that description is saved permanently so the next identical invoice line matches instantly with 100% confidence.
            </p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4 space-y-2">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">📋 Inventory Lot</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                A batch of a product received from one pallet line, with unit cost and remaining quantity. Deductions
                (from shows) pull from the oldest active lot first (FIFO), giving you accurate cost of goods.
            </p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4 space-y-2">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">📂 Receiving Session</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                The root of every shipment. Groups one or more pallets together and tracks the full import audit —
                who received it, when, from which file, and how many lines matched automatically vs. needed review.
                Sessions can be reopened if you need to make corrections.
            </p>
        </div>
    </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════════════════ --}}
{{-- AI MATCHING                                                                 --}}
{{-- ════════════════════════════════════════════════════════════════════════════ --}}
@if($tab === 'matching')
<div class="space-y-5">

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-5">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">Three-Stage Matching Pipeline</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            AI is only called when cheaper methods fail. The pipeline runs from fastest to most powerful — and gets
            smarter with every confirmation you make, without retraining anything.
        </p>
    </div>

    {{-- Stage cards --}}
    @foreach([
        ['1', 'violet', 'Alias Lookup', '0 ms · 100%', 'Instant',
            'Checks your saved alias table first. Once you confirm "TC Hobby" = 2025 Topps Chrome Hobby Box, that mapping is stored forever. The next invoice with that exact description matches immediately — no AI call needed.',
            '"TC Hobby" → Product #42 (confirmed 14 times)'],
        ['2', 'blue', 'Fuzzy Text Matching', '5–20 ms · up to 95%', 'Local · No GPU',
            'Tokenizes and scores the description against your product catalog using token overlap (Jaccard similarity), Levenshtein distance, and bonuses for matching year, brand, and sport fields. Runs in PHP — no Ollama required.',
            '"2025 TOPPS CHROME HOBBY" → scores 0.91 against "2025 Topps Chrome Baseball Hobby Box"'],
        ['3', 'indigo', 'Embedding Similarity', '50–200 ms · up to 90%', 'Requires Ollama',
            'Converts the vendor description to a 768-dimension vector using nomic-embed-text and finds the nearest products by cosine similarity. Catches abbreviations and paraphrases that fuzzy matching misses. Ollama must be running.',
            '"\'25 Prizm FB" → embedding distance 0.87 to "2025 Panini Prizm Football Hobby Box"'],
    ] as [$num, $color, $name, $speed, $req, $desc, $example])
    <div class="rounded-xl border border-{{ $color }}-200 dark:border-{{ $color }}-800 bg-white dark:bg-gray-900 overflow-hidden">
        <div class="flex items-center gap-4 px-5 py-3 bg-{{ $color }}-50 dark:bg-{{ $color }}-950">
            <span class="flex-shrink-0 w-8 h-8 rounded-full bg-{{ $color }}-600 text-white text-sm font-bold flex items-center justify-center">{{ $num }}</span>
            <div class="flex-1">
                <p class="text-sm font-semibold text-{{ $color }}-900 dark:text-{{ $color }}-100">{{ $name }}</p>
                <p class="text-xs text-{{ $color }}-600 dark:text-{{ $color }}-400">{{ $speed }} · {{ $req }}</p>
            </div>
        </div>
        <div class="px-5 py-4 space-y-2">
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $desc }}</p>
            <div class="rounded-lg bg-gray-50 dark:bg-gray-800 px-4 py-2 font-mono text-xs text-gray-500 dark:text-gray-400">
                {{ $example }}
            </div>
        </div>
    </div>
    @endforeach

    {{-- Confidence buckets --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-5">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Confidence Levels</h3>
        <div class="space-y-2">
            @foreach([
                ['100%', 'green', 'Exact', 'Saved alias match — accepted automatically, no UI shown.'],
                ['95–99%', 'green', 'Auto-Accept', 'High confidence fuzzy or embedding match — goes to the ✅ Auto-Matched bucket. Use "Confirm All" or it\'s already applied.'],
                ['80–94%', 'amber', 'Needs Review', 'Reasonable match but uncertain — you confirm or correct in the ⚠ Review section.'],
                ['< 80%', 'blue', 'New Product', 'No usable match — you create a new product or map it to an existing one.'],
            ] as [$range, $color, $label, $desc])
            <div class="flex items-start gap-4">
                <span class="flex-shrink-0 w-20 text-right text-sm font-mono font-semibold text-{{ $color }}-600 dark:text-{{ $color }}-400">{{ $range }}</span>
                <span class="flex-shrink-0 inline-flex items-center rounded-full bg-{{ $color }}-100 dark:bg-{{ $color }}-900 px-2 py-0.5 text-xs font-medium text-{{ $color }}-700 dark:text-{{ $color }}-300 mt-0.5">{{ $label }}</span>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $desc }}</p>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Learning note --}}
    <div class="rounded-xl border border-violet-200 dark:border-violet-800 bg-violet-50 dark:bg-violet-950 px-5 py-4 flex gap-3">
        <x-heroicon-o-arrow-trending-up class="h-5 w-5 text-violet-500 flex-shrink-0 mt-0.5" />
        <div>
            <p class="text-sm font-semibold text-violet-900 dark:text-violet-100">The system gets smarter over time</p>
            <p class="text-sm text-violet-700 dark:text-violet-300 mt-1">
                Every time you confirm a match, the description is saved as an alias with a confidence score.
                After a few confirmations, the same vendor description returns 100% confidence instantly — Ollama
                isn't called at all. Over time, common vendors stop requiring any AI involvement.
            </p>
        </div>
    </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════════════════ --}}
{{-- SCANNER MODES                                                               --}}
{{-- ════════════════════════════════════════════════════════════════════════════ --}}
@if($tab === 'scanner')
<div class="space-y-5">

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-5">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">Scanner — Three Modes</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            The scanner page is designed for warehouse use — add it to your phone's home screen from your browser
            (Add to Home Screen) for a full-screen native-app feel. Works with any Bluetooth or USB barcode scanner,
            or the phone's camera.
        </p>
    </div>

    @foreach([
        ['violet', '🔍', 'Look Up', 'What is this item?',
            'Scan or type any barcode, UPC, or SKU. Instantly shows the product name, total stock across all locations, average cost, reorder status, and the last 5 movements. Tap "Adjust Stock" to make a quick correction.',
            ['Scan a box to check remaining inventory', 'Look up avg cost before pricing a lot', 'Spot-check stock levels during a show', 'Quickly adjust for discovered damage']],
        ['emerald', '➕', 'Quick Add', 'Add units to stock fast',
            'Set a destination location and units-per-scan, then scan repeatedly. Each scan credits the quantity immediately. Perfect for adding unboxed singles or filling a location without a full receiving session.',
            ['Add returns to a storage location', 'Transfer units during reorganization', 'Quickly count in a new sub-lot', 'Add bonus inventory from a trade']],
        ['blue', '📦', 'Receive Pallet', 'Receive cases by scanning items',
            'Select a pallet that\'s been mapped in a receiving session, then scan each product\'s item barcode (not the case barcode). All expected cases for that line are received at once. The progress checklist updates live so you can see which lines still need to be scanned.',
            ['Receive a whole pallet in under 2 minutes', 'Progress checklist shows ✓ / ⚠ / ○ per line', 'Scanner-friendly: scan → confirm → next line', 'Works for partial receives too']],
    ] as [$color, $emoji, $title, $subtitle, $desc, $useCases])
    <div class="rounded-xl border border-{{ $color }}-200 dark:border-{{ $color }}-800 bg-white dark:bg-gray-900 overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-3 bg-{{ $color }}-50 dark:bg-{{ $color }}-950">
            <span class="text-2xl">{{ $emoji }}</span>
            <div>
                <p class="text-sm font-semibold text-{{ $color }}-900 dark:text-{{ $color }}-100">{{ $title }}</p>
                <p class="text-xs text-{{ $color }}-600 dark:text-{{ $color }}-400">{{ $subtitle }}</p>
            </div>
        </div>
        <div class="px-5 py-4 space-y-3">
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $desc }}</p>
            <ul class="space-y-1">
                @foreach($useCases as $uc)
                <li class="flex items-start gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <span class="text-{{ $color }}-400 flex-shrink-0 mt-0.5">▸</span>{{ $uc }}
                </li>
                @endforeach
            </ul>
        </div>
    </div>
    @endforeach

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4 flex gap-3">
        <x-heroicon-o-device-phone-mobile class="h-5 w-5 text-gray-400 flex-shrink-0 mt-0.5" />
        <div>
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Add to Home Screen (PWA)</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                In Chrome or Safari, tap the browser menu and choose <em>Add to Home Screen</em>.
                VortexOps opens in full-screen mode — no browser chrome — and works offline for lookups
                (stock data is cached). The scanner icon appears on your home screen like any other app.
            </p>
        </div>
    </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════════════════ --}}
{{-- PRODUCT CATALOG                                                             --}}
{{-- ════════════════════════════════════════════════════════════════════════════ --}}
@if($tab === 'products')
<div class="space-y-5">

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-5">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">Product Catalog</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Products don't need to be complete on day one. Start with just the name — add brand, sport, year,
            and UPC over time as products come through receiving.
        </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @foreach([
            ['Required', 'green', ['name' => 'Product name (the canonical title)', 'is_active' => 'Active flag (hide discontinued items)']],
            ['Card Fields', 'blue', ['brand' => 'Topps, Panini, Upper Deck…', 'sport' => 'Baseball, Football, Basketball…', 'year' => 'Release year (improves matching)', 'set_name' => 'Chrome, Prizm, Bowman…', 'product_type' => 'Hobby Box, Blaster, Jumbo, Retail…', 'configuration' => '12 packs/box, 36 packs/box…']],
            ['Identifiers', 'violet', ['upc' => 'Manufacturer UPC — the most reliable ID', 'sku' => 'Your internal SKU (optional)', 'barcode' => 'Physical box barcode if different from UPC']],
            ['Cost & Stock', 'amber', ['unit_cost' => 'Default cost used for new lots', 'average_cost' => 'Weighted average across all lots (auto-calculated)', 'reorder_level' => 'Triggers low-stock alert when quantity falls below this']],
        ] as [$section, $color, $fields])
        <div class="rounded-xl border border-{{ $color }}-200 dark:border-{{ $color }}-800 bg-white dark:bg-gray-900 overflow-hidden">
            <div class="px-4 py-2.5 bg-{{ $color }}-50 dark:bg-{{ $color }}-950">
                <p class="text-xs font-semibold text-{{ $color }}-700 dark:text-{{ $color }}-300 uppercase tracking-wide">{{ $section }}</p>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($fields as $field => $desc)
                <div class="px-4 py-2.5 flex gap-3 text-sm">
                    <code class="flex-shrink-0 text-xs bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 px-1.5 py-0.5 rounded font-mono">{{ $field }}</code>
                    <span class="text-gray-500 dark:text-gray-400">{{ $desc }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4 space-y-2">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Scanning by UPC</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            The barcode printed on a sports card box (EAN-13 or UPC-A) is the manufacturer's product code.
            It's the same for every copy of that product from any distributor. Storing this in the <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded text-xs">upc</code> field
            makes the scanner instant and 100% accurate — no description matching needed.
            You can scan the box UPC right into the field when editing the product record.
        </p>
    </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════════════════ --}}
{{-- SHOWS & DEDUCTIONS                                                          --}}
{{-- ════════════════════════════════════════════════════════════════════════════ --}}
@if($tab === 'shows')
<div class="space-y-5">

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-5">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">Shows & Inventory Deductions</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            When a Whatnot show is complete, inventory is deducted by Product — not by vendor SKU or lot.
            The system automatically selects which lot to draw from using your configured policy.
        </p>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-5 space-y-4">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Deduction Flow</h3>
        <div class="space-y-3">
            @foreach([
                ['1', 'Show runs on Whatnot', 'Live breaks sell products. Whatnot data is imported automatically via the scraper.'],
                ['2', 'Deduction Request created', 'Each product used in the show becomes a deduction line. Quantity is matched to inventory.'],
                ['3', 'Lot selection (FIFO)', 'The oldest active inventory lot for that product is drawn down first, preserving cost basis.'],
                ['4', 'Movement logged', 'An InventoryMovement record is created with type "sale_deduction", quantity, lot reference, and show reference for full traceability.'],
                ['5', 'Stock updated', 'InventoryStock.quantity decremented. If a lot reaches zero, it\'s marked depleted automatically.'],
            ] as [$n, $title, $desc])
            <div class="flex gap-4">
                <span class="flex-shrink-0 w-7 h-7 rounded-full bg-violet-600 text-white text-xs font-bold flex items-center justify-center">{{ $n }}</span>
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $title }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $desc }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @foreach([
            ['FIFO', 'green', 'First In, First Out', 'Oldest lot deducted first. Default. Best for accurate COGS when prices fluctuate.'],
            ['LIFO', 'amber', 'Last In, First Out', 'Newest lot deducted first. Useful when recent costs reflect current market.'],
            ['Cheapest', 'blue', 'Lowest Cost First', 'Deducts from the lot with the lowest unit cost. Minimizes COGS.'],
        ] as [$name, $color, $label, $desc])
        <div class="rounded-xl border border-{{ $color }}-200 dark:border-{{ $color }}-800 bg-{{ $color }}-50 dark:bg-{{ $color }}-950 px-4 py-4">
            <p class="text-sm font-bold text-{{ $color }}-700 dark:text-{{ $color }}-300">{{ $name }}</p>
            <p class="text-xs font-medium text-{{ $color }}-600 dark:text-{{ $color }}-400 mb-1">{{ $label }}</p>
            <p class="text-xs text-{{ $color }}-700 dark:text-{{ $color }}-300">{{ $desc }}</p>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════════════════ --}}
{{-- TIPS & TRICKS                                                               --}}
{{-- ════════════════════════════════════════════════════════════════════════════ --}}
@if($tab === 'tips')
<div class="space-y-4">

    @foreach([
        ['heroicon-o-qr-code', 'violet', 'Scan UPCs into product records',
            'When creating or editing a product, click into the UPC field and scan the barcode on the box. This takes 2 seconds and makes every future scanner lookup instant — no typing, no matching, 100% accurate.'],
        ['heroicon-o-arrow-trending-up', 'green', 'Match confidence improves automatically',
            'You never have to do anything special — just confirm matches as normal. Each confirmation increments the alias confidence counter. After 5+ confirmations, a vendor description matches at 100% with zero latency. Common vendors become "free" over time.'],
        ['heroicon-o-document-duplicate', 'blue', 'Reopen sessions for corrections',
            'If you realize a line was mapped incorrectly after completing a session, use the Reopen action on the session. This puts it back into Reviewing status so you can re-map lines. Inventory movements are linked, so corrections flow through automatically.'],
        ['heroicon-o-inbox-stack', 'amber', 'One session per shipment',
            'Start a new Receiving Session for each purchase order or shipment. This gives you a clean audit trail: one session = one vendor invoice = one set of lots. You can always see which session a lot came from.'],
        ['heroicon-o-cpu-chip', 'indigo', 'Run Ollama for better matching',
            'The matching pipeline works without Ollama (Stages 1 and 2 are pure PHP), but Stage 3 embedding similarity catches abbreviations and paraphrases that fuzzy matching misses. Run `ollama pull nomic-embed-text` once and leave Ollama running — the system uses it automatically when available.'],
        ['heroicon-o-device-phone-mobile', 'gray', 'Use the scanner PWA in the warehouse',
            'Add VortexOps to your phone\'s home screen (Add to Home Screen in Chrome/Safari). It opens full-screen with no browser UI. Connect a Bluetooth scanner and you can receive a full pallet in under 2 minutes without touching the screen.'],
    ] as [$icon, $color, $title, $body])
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4 flex gap-4">
        <x-dynamic-component :component="$icon" class="h-5 w-5 text-{{ $color }}-500 flex-shrink-0 mt-0.5" />
        <div>
            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $body }}</p>
        </div>
    </div>
    @endforeach

</div>
@endif

</div>
</x-filament-panels::page>
