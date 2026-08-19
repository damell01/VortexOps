<x-filament-panels::page>
@php($status = $this->locationStatus)
<div class="space-y-6">

{{-- Guided tours run once per person. This is how someone gets them back —
     either because they skipped them or because they are new to this account. --}}
<div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-violet-200 dark:border-violet-800 bg-violet-50 dark:bg-violet-950/40 px-5 py-4">
    <div>
        <h3 class="text-sm font-semibold text-violet-900 dark:text-violet-200">Guided tours</h3>
        <p class="text-sm text-violet-800 dark:text-violet-300">
            Several screens introduce themselves the first time you open them. Each one can be replayed
            from the <strong>“? How this page works”</strong> button next to its title.
        </p>
    </div>
    <button type="button" id="vx-replay-tours"
        class="flex-shrink-0 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-violet-700 transition">
        Show all tours again
    </button>
</div>

<script>
document.getElementById('vx-replay-tours')?.addEventListener('click', async function () {
    this.disabled = true;
    const original = this.textContent;

    try {
        const res = await fetch(@js(route('tours.reset')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
        });

        this.textContent = res.ok ? 'Tours reset — open any screen' : 'Could not reset';
    } catch {
        this.textContent = 'Could not reset';
    }

    setTimeout(() => { this.textContent = original; this.disabled = false; }, 4000);
});
</script>

{{-- ── Tab strip ────────────────────────────────────────────────────────────── --}}
<div class="flex rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-1 gap-1 overflow-x-auto">
    @foreach([
        'start'   => ['📍', 'Start Here'],
        'items'   => ['➕', 'Add & Edit Items'],
        'restock' => ['📷', 'Restock & Scan'],
        'pallets' => ['🚚', 'Receive a Pallet'],
        'costs'   => ['💵', 'Costs & Value'],
        'fix'     => ['🔀', 'Fixing Mistakes'],
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

    <x-guide.panel title="Three things, and they are not the same thing">
        <p class="mb-3">
            Almost every confusion in inventory comes from these being blurred together.
        </p>
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

    {{-- Read from this install, so the advice is either true here or visibly wrong. --}}
    @if($status['count'] === 0)
        <x-guide.panel tone="amber" title="⚠ You have no active locations">
            <p>
                Stock has nowhere to go, which is exactly why location dropdowns elsewhere look empty.
                Create one before anything else — steps below.
            </p>
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
            <p>
                {{ $status['count'] }} active {{ Str::plural('location', $status['count']) }}, including general storage.
                Nothing to do on this tab.
            </p>
        </x-guide.panel>
    @endif

    <x-guide.steps title="Creating a location" :steps="[
        ['Open Inventory → Locations', 'Admin only. If it is missing from your sidebar that is a role, not a bug — ask the owner rather than working around it.'],
        ['New → name it Main Storage → type Main Storage', 'There is no type called warehouse. Main Storage is the general-purpose one.'],
        ['Leave the status Active', 'Inactive locations vanish from every dropdown in the app. That is the second reason a list looks empty, and it looks identical to the first.'],
        ['Add your other real places', 'One row per physical place. Resist inventing locations that are really statuses — Damaged and Returned already exist as types.'],
    ]" />

    <x-guide.table title="The six location types" :rows="[
        ['Main Storage', 'General stock. The default answer, and where receiving lands unless told otherwise.'],
        ['Streamer Inventory', 'Assigned to one streamer, who sees only their own. Never use this for a shared shelf.'],
        ['Fulfillment', 'Picked and waiting to ship. Separating it stops packed orders being counted as sellable.'],
        ['Returned', 'Came back from a buyer or is going back to a vendor. Out of sellable stock, still tracked.'],
        ['Damaged', 'Unsellable. Kept visible so damage reads as damage rather than as stock quietly disappearing.'],
        ['Other', 'Anything genuinely none of the above. If you reach for this often, a type is probably missing.'],
    ]" />

    <x-guide.panel tone="violet" title="Set a default receiving location">
        <p>
            Receiving asks where stock goes for every line. Set a default under Settings and it stops asking —
            the same answer typed repeatedly is the one worth configuring. With exactly one active location,
            that one is used automatically.
        </p>
    </x-guide.panel>

</div>
@endif

{{-- ══════════════════════════════════════════════════════════ ADD & EDIT ITEMS --}}
@if($tab === 'items')
<div class="space-y-6">

    <x-guide.panel title="Search before you create. Every time.">
        <p>
            Duplicate items are the most expensive mistake on this screen and the hardest to unpick: two rows,
            two stock counts, two averaged costs, and reports that quietly understate both. Search the name,
            the SKU and the barcode before adding anything.
        </p>
    </x-guide.panel>

    <x-guide.cards :cards="[
        ['Quick Add', 'Three steps. Name is the only required field. Use it when you are holding the thing and want it recorded now — vendor and reorder levels can wait.'],
        ['Create Inventory Item', 'Four steps, every field. Use it when setting a product up properly, especially one you will reorder.'],
        ['From a pallet line', 'On an unmapped line, create the item straight from it. The item is mapped to that line immediately, so the packing-slip wording stays connected to the product.'],
        ['Container breakdown', 'A case that splits into boxes is an item whose child item and units-per-parent are set. Breaking one down converts stock without inventing or losing value.'],
    ]" />

    <x-guide.table title="What each field is for" :rows="[
        ['Name', 'What people search for. Say it the way you would out loud. A bad name costs someone thirty seconds every time they look for it — fix it early.'],
        ['SKU', 'Optional and unique. Leave it blank if you do not have one; blank is fine on any number of items.'],
        ['Barcode / UPC', 'Optional and unique. This is what a scan resolves to, so it is worth capturing on anything you will scan twice.'],
        ['Category', 'Groups items for filtering and reporting. Consistency matters more than precision — pick a scheme and keep to it.'],
        ['Unit cost', 'What you expect to pay. Seeds new receipts. It does not rewrite the value of stock already on the shelf.'],
        ['Average cost', 'Calculated, not typed. Every receipt re-blends it across everything on hand.'],
        ['Reorder level', 'The count at which this should be reordered. Drives the low-stock views — leave it at zero and the item never appears there.'],
        ['Unit type', 'Box, case, pack. Cosmetic, but it makes quantities readable at a glance.'],
        ['Preferred vendor', 'Who you normally buy it from. Speeds up receiving and shows where to reorder.'],
        ['Photo', 'Worth adding for anything visually similar to something else. Recompressed on upload, so a phone photo is fine.'],
        ['Active', 'Turn off instead of deleting when you stop carrying something. Deleting takes its history with it.'],
    ]" />

    <x-guide.panel tone="amber" title="Stock is not edited on the item">
        <p>
            There is no quantity box on the item form, deliberately. Stock changes through receiving, Quick Add Stock,
            transfers and reconciliation — each of which records what changed, why, and who did it. A directly
            editable number would have no reason attached, and the reason is the part you need when the count is
            questioned three weeks later.
        </p>
    </x-guide.panel>

    <x-guide.panel tone="violet" title="If a barcode is refused">
        <p>
            "Already on another item" is not an obstacle to route around — it is the system telling you the item
            exists. Search that barcode in All Inventory and you will find the row you were about to duplicate.
        </p>
    </x-guide.panel>

</div>
@endif

{{-- ═══════════════════════════════════════════════════════════ RESTOCK & SCAN --}}
@if($tab === 'restock')
<div class="space-y-6">

    <x-guide.panel title="Adding more of something you already carry">
        <p>
            The everyday job, and a different screen from adding an item. Use <strong>Quick Add Stock</strong>
            when a few boxes arrive outside a delivery — a local pickup, a trade, something found on a shelf.
            For a whole shipment, use receiving instead so the freight lands on the cost.
        </p>
    </x-guide.panel>

    <x-guide.steps title="Quick Add Stock, start to finish" :steps="[
        ['Scan or type the barcode', 'The item appears. If nothing appears, that code is not on file — add the item first, then come back.'],
        ['Choose the location', 'Where these are physically going. Not where they usually live — where they are going now.'],
        ['Enter the quantity', 'How many you are adding, not the new total. This is the difference between adding six and setting six.'],
        ['Set a unit cost only if it differs', 'Leave it blank and the item\'s own cost is used, which is right most of the time. Fill it in when this batch genuinely cost something else.'],
        ['Add, and check the recent list', 'Recent additions stay on screen. Glance at them — it is the cheapest way to catch a double scan.'],
    ]" />

    <x-guide.table title="Two ways to scan" :rows="[
        ['Camera', 'Any 📷 button. Works on a phone at the shelf and on a laptop with a webcam. Grant camera permission once, per browser.'],
        ['Scanner gun', 'Behaves like a keyboard. Click into the barcode field and scan. Faster for a stack, and it never argues about focus or lighting.'],
    ]" />

    <x-guide.table title="What a scan does depends on where you are" :rows="[
        ['Receiving a pallet', 'Receives that case against its line and moves the received count up by one.'],
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

{{-- ══════════════════════════════════════════════════════════ RECEIVE A PALLET --}}
@if($tab === 'pallets')
<div class="space-y-6">

    <x-guide.panel title="A pallet is one delivery">
        <p>
            Use receiving for anything arriving as a shipment. It keeps the vendor, PO, freight and fees attached
            to the stock, which is what makes each box's real cost correct. Adding the same boxes one at a time
            through Quick Add gets the counts right and the costs wrong — and the costs are what the profit
            numbers are built on.
        </p>
    </x-guide.panel>

    <x-guide.steps title="Start to finish" :steps="[
        ['Create the pallet', 'Purchasing → Pallets → New. Vendor and a reference such as the PO number. Add carrier and tracking if you have them.'],
        ['Enter shipping and fees', 'On the pallet, not in your head. These spread across the lines and are the gap between what the invoice says and what a box actually cost you.'],
        ['List what you expect', 'One line per product: description, case count, units per case, unit cost. Type names freely — the item does not have to exist yet.'],
        ['Map each line to an item', 'Point the line at a real item, or create one from the line. This connects a vendor\'s wording to your product. An unmapped line cannot be received.'],
        ['Receive the stock', 'Scan each case as you unload, or Receive All on a line you counted by hand. Counts climb as you go, so you can stop and come back tomorrow.'],
        ['Review before closing', 'Compare expected against received. A shortfall stands out here rather than surfacing in a count weeks later with no explanation.'],
        ['Close it', 'Credits stock to each line\'s location. If a line is short and not coming, close it short — that records the shortfall instead of leaving a pallet open forever.'],
    ]" />

    <x-guide.table title="Fields worth getting right" :rows="[
        ['Reference', 'Your PO or invoice number. This is how you find the pallet again when a vendor calls about it.'],
        ['Case count × units per case', 'Two numbers, not one. Twelve cases of six is not the same as seventy-two loose boxes when you come to scan them.'],
        ['Unit cost', 'Per unit, not per case, unless the line is genuinely sold by the case. Getting this backwards is the most common costing error.'],
        ['Shipping and fees', 'Pallet-level. Spread across lines by value, so they land on the expensive stock proportionally.'],
        ['Is container', 'Marks a line that arrives as a case and gets broken down later. Sets up the breakdown rather than doing it now.'],
        ['Location', 'Per line. Leave it and the default receiving location is used.'],
    ]" />

    <x-guide.panel tone="violet" title="Photograph it while you are standing there">
        <p>
            Attach photos of the pallet as it arrived, the packing slip, and any damage — from the pallet page,
            on the day. A dispute three weeks later is settled by what you photographed, and nobody has ever
            regretted taking the extra picture.
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
            by quantity. So a cost typed wrong does not just mis-price that delivery; it moves the value of stock
            you already had. It is the one number worth slowing down for.
        </p>
    </x-guide.panel>

    <x-guide.table title="Where cost comes from" :rows="[
        ['Line unit cost', 'What the vendor charged. The main input, and the one you can check against the invoice.'],
        ['Shipping and fees', 'Entered on the pallet and spread across its lines. This is why freight belongs on the pallet.'],
        ['Stock unit cost override', 'On Quick Add Stock, for a batch that cost something different. Blank means use the item\'s own cost.'],
        ['Item unit cost', 'The expected price. Seeds receipts that do not say otherwise; never rewrites existing stock.'],
    ]" />

    <x-guide.panel tone="violet" title="Stock is consumed oldest first">
        <p>
            When boxes are broken on stream, the oldest are used first, at the cost they were received at. That is
            what makes a show's profit reflect what those specific boxes cost rather than what the same box costs
            today — and why a receipt entered at the wrong cost keeps affecting shows long after it was entered.
        </p>
    </x-guide.panel>

    <x-guide.steps title="If the value looks wrong" :steps="[
        ['Check the receipts, not the item', 'Average cost is derived. The item\'s unit cost field is not what produced it.'],
        ['Look for a zero', 'A batch received at $0.00 drags the average down across everything on hand. It is the single most common cause.'],
        ['Check per-unit versus per-case', 'A case cost entered as a unit cost inflates value by the case size, and vice versa.'],
        ['Confirm freight landed once', 'Shipping entered on the pallet and again on a line counts it twice.'],
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

    <x-guide.table title="Reason → tool" :rows="[
        ['It moved to another shelf', 'Stock Transfer. Out of one location, into another. Totals unchanged.'],
        ['The count was simply wrong', 'Inventory Reconciliation. Enter what is actually there; the difference is recorded as an adjustment with who and when.'],
        ['It arrived or became damaged', 'Mark Damaged. Moves it out of sellable stock and keeps it visible, so damage reads as damage rather than shrinkage.'],
        ['It is going back to the vendor', 'Move to Returns. Separates it from sellable stock while you wait on the credit.'],
        ['A pallet line never turned up', 'Close pallet short. Records the shortfall against that line instead of leaving the pallet open indefinitely.'],
        ['A case needs splitting into boxes', 'Break down the container. Converts stock from parent to child without inventing or losing value.'],
    ]" />

    <x-guide.panel tone="amber" title="Never transfer stock you cannot find">
        <p>
            Moving missing boxes into a Damaged location makes the totals look right and hides a real discrepancy.
            Reconcile instead. The adjustment <em>is</em> the record that something went missing, and that record is
            the entire point — a tidy number with no explanation is worse than an untidy one with a reason.
        </p>
    </x-guide.panel>

    <x-guide.panel tone="violet" title="Reconcile a shelf at a time">
        <p>
            Counting everything at once produces a long list of small discrepancies nobody investigates. Counting
            one location produces a short list somebody actually chases down.
        </p>
    </x-guide.panel>

</div>
@endif

{{-- ═════════════════════════════════════════════════════════ TROUBLESHOOTING --}}
@if($tab === 'trouble')
<div class="space-y-6">

    <x-guide.qa :items="[
        ['The stock location dropdown is empty', 'No active locations exist, or the ones that do are marked inactive — inactive locations are hidden everywhere. See Start Here.'],
        ['I cannot find a main warehouse anywhere', 'The general location is called Main Storage, not warehouse. If it is genuinely absent, create it. Do not repurpose a streamer location: those are filtered per streamer, so a shared shelf would vanish for everyone else.'],
        ['That barcode is already on another item', 'Working as intended. Barcodes are unique so a scan resolves to exactly one product. Search it in All Inventory to find the item that owns it.'],
        ['The camera button does nothing', 'Camera access is per site and per browser. Open the padlock beside the address bar, allow the camera, reload. On a machine with no camera, use a scanner gun.'],
        ['Stock is right but the value looks wrong', 'Check the receipts rather than the item. One batch received at $0.00 drags the average across everything on hand. See Costs & Value.'],
        ['I added an item twice by mistake', 'Move any stock off the duplicate with a transfer, then deactivate it rather than deleting — deleting takes its movement history with it.'],
        ['A pallet will not close', 'Every line has to be mapped to an item and received or closed short. An unmapped line is the usual culprit.'],
        ['A streamer cannot see stock I can see', 'Streamer Inventory locations are filtered to their owner. If they should see it, it belongs somewhere that is not a streamer location.'],
        ['Quick Add threw an Internal Server Error', 'A bug on the final step discarded everything on submit — which is why scanning appeared not to work: nothing saved, rather than the scan failing. Fixed; if you still see it, this server has not been updated.'],
    ]" />

</div>
@endif

</div>
</x-filament-panels::page>
