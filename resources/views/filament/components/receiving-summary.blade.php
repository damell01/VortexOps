<div class="space-y-4">
    <p class="text-sm text-gray-500">Scanned receipts are already saved. Pause to wait for the remaining delivery, or complete only after physically verifying every remaining case.</p>
    <div class="grid grid-cols-2 gap-3">
        @foreach(['expected_units' => 'Expected units', 'confirmed_units' => 'Received units', 'short_units' => 'Outstanding units'] as $key => $label)
            <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800"><p class="text-xs">{{ $label }}</p><strong class="text-lg">{{ number_format($review['totals'][$key], 2) }}</strong></div>
        @endforeach
        <div class="rounded-xl bg-amber-50 p-3 dark:bg-amber-950"><p class="text-xs">Lines needing cost review</p><strong class="text-lg">{{ collect($review['lines'])->where('unit_cost', '<=', 0)->count() }}</strong></div>
    </div>
    @foreach($review['blockers'] as $blocker)<p class="text-sm text-red-600">{{ $blocker }}</p>@endforeach
    @foreach($review['lines'] as $line)
        <article class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
            <strong>{{ $line['name'] }}</strong>
            <p class="mt-1 text-sm">{{ $line['confirmed_cases'] }} / {{ $line['expected_cases'] }} cases received · {{ $line['location'] ?: 'Location not set' }}</p>
            @if($line['variance_cases'] < 0)<p class="text-sm text-amber-700">{{ abs($line['variance_cases']) }} case(s) still outstanding</p>@endif
            @if($line['variance_cases'] > 0)<p class="text-sm text-amber-700">{{ $line['variance_cases'] }} extra case(s) — review the manifest</p>@endif
            @if($line['unit_cost'] <= 0)<p class="text-sm text-amber-700">No positive unit cost recorded — review before completion.</p>@else<p class="text-xs text-gray-500">Unit cost ${{ number_format($line['unit_cost'], 2) }}</p>@endif
        </article>
    @endforeach
</div>
