<x-filament-panels::page>
    <div class="space-y-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-600">How VortexOps Is Meant To Work</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Inventory, shows, deductions, and payouts all connect</h1>
            <p class="mt-3 max-w-4xl text-sm text-slate-600">
                The key idea is that VortexOps tracks your sealed product inventory at the <strong>case level</strong>. Pallets are just inbound receiving containers that help you intake, break down, and move those cases into the right storage locations.
            </p>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1.05fr,0.95fr]">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">Core operating flow</h2>
                <div class="mt-5 space-y-4">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">1. Receive inventory</p>
                        <p class="mt-2 text-sm text-slate-700">
                            A distributor shipment comes in. You receive it as a pallet or inbound case, attach known cost details, and place it into a receiving location.
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">2. Break pallets into cases</p>
                        <p class="mt-2 text-sm text-slate-700">
                            If a shipment arrives on a pallet, you split that pallet into the actual case containers your team will store and sell from later.
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">3. Put cases into locations</p>
                        <p class="mt-2 text-sm text-slate-700">
                            Cases then get assigned to their real inventory locations, like main storage, fulfillment, or a streamer-specific location.
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">4. Log a show</p>
                        <p class="mt-2 text-sm text-slate-700">
                            A show record captures the stream itself: title, date, streamer(s), revenue, tips, and overall operational notes.
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">5. Review sold inventory</p>
                        <p class="mt-2 text-sm text-slate-700">
                            Deductions are reviewed before stock is touched. That is how the system decides which cases or case-based inventory quantities should be reduced after a show.
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">6. Calculate payouts</p>
                        <p class="mt-2 text-sm text-slate-700">
                            Once shows are reconciled and deductions are approved, payouts can be generated from the show financials and streamer payout rules.
                        </p>
                    </div>
                </div>
            </section>

            <div class="space-y-6">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">Important inventory assumption</h2>
                    <div class="mt-4 rounded-2xl border border-cyan-200 bg-cyan-50 p-4 text-sm text-slate-700">
                        <p class="font-semibold text-slate-950">Cases are the actual inventory unit.</p>
                        <p class="mt-2">
                            You are not trying to track every individual card. The system should treat a sealed case as the real inventory object you receive, store, move, and deduct from operationally.
                        </p>
                    </div>
                    <ul class="mt-4 space-y-3 text-sm text-slate-600">
                        <li>Pallets are mainly for inbound receiving and breakdown.</li>
                        <li>Cases are what should end up in inventory locations.</li>
                        <li>Show deductions should ultimately connect back to those case-based inventory quantities.</li>
                    </ul>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">What manual show entry means</h2>
                    <p class="mt-3 text-sm text-slate-600">
                        Manual show entry should create the show record first, not force the entire inventory deduction process into the same form.
                    </p>
                    <div class="mt-4 space-y-3">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                            <strong>Show form:</strong> title, date, streamers, gross revenue, tips, show-level notes, and optionally the sold inventory items if ops already knows what moved.
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                            <strong>Sold items step:</strong> after the show exists, use the <strong>Enter Sold Items</strong> action on the show to choose what cases or sealed products were actually sold.
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                            <strong>Deduction review:</strong> confirms what inventory should actually come out after the show.
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                            <strong>Cost basis:</strong> comes from the inventory item and intake costing fields, not from retyping cost every time a show is logged.
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">Next workflow layer to build</h2>
                    <p class="mt-3 text-sm text-slate-600">
                        The clean next step is a proper distributor receipt / purchase intake flow:
                    </p>
                    <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                        Distributor receipt
                        <span class="mx-2 text-slate-400">→</span>
                        line items
                        <span class="mx-2 text-slate-400">→</span>
                        pallet / case receipt
                        <span class="mx-2 text-slate-400">→</span>
                        case location assignment
                        <span class="mx-2 text-slate-400">→</span>
                        show deductions later
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
