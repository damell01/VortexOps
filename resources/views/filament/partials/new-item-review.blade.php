@php
    $groups = [
        'Item identity' => [
            'Item name' => $name ?: 'Not entered',
            'SKU' => $sku ?: 'Generated automatically',
            'Barcode / UPC' => $barcode ?: 'Not added',
            'Category' => $category ?: 'Uncategorized',
        ],
        'Pricing & stock' => [
            'Unit cost' => '$' . number_format((float) ($unitCost ?? 0), 2),
            'Opening stock' => number_format((float) ($quantity ?? 0)) . ' units',
            'Reorder level' => filled($reorder) ? number_format((float) $reorder) . ' units' : 'Not set',
        ],
        'Item settings' => [
            'Item type' => $container ? 'Container / case' : 'Single item',
            'Status' => $active ? 'Active' : 'Inactive',
        ],
    ];
@endphp
<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary-200 bg-primary-50 p-4 dark:border-primary-800 dark:bg-primary-950/30">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-300">Final review</p>
            <h3 class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ $name ?: 'Unnamed inventory item' }}</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Confirm these details before creating the item.</p>
        </div>
        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $active ? 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200' }}">{{ $active ? 'Active' : 'Inactive' }}</span>
    </div>
    @if (blank($name))
        <div role="alert" class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm font-medium text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200">
            Item name is required. Return to Basic Info to enter a name before saving.
        </div>
    @endif
    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($groups as $heading => $rows)
            <section class="min-w-0 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h4 class="border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm font-bold text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white">{{ $heading }}</h4>
                <dl class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($rows as $label => $value)
                        <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1 px-4 py-3">
                            <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                            <dd class="max-w-full break-words text-right text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endforeach
    </div>
    @if ($container)
        <p class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-200">After saving, add the items inside this container using Container Scan.</p>
    @endif
</div>
