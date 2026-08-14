@php
    $rows = array_filter([
        'Item Name'      => $name ?: null,
        'SKU'            => $sku ?: null,
        'Barcode / UPC'  => $barcode ?: null,
        'Category'       => $category ?: null,
        'Type'           => $container ? 'Container (holds other items)' : 'Single item',
        'Unit Cost'      => filled($unitCost) ? '$' . number_format((float) $unitCost, 2) : null,
        'Reorder Level'  => filled($reorder) ? number_format((float) $reorder) . ' units' : null,
        'Initial Stock'  => filled($quantity) ? number_format((float) $quantity) . ' units' : null,
        'Status'         => $active ? 'Active' : 'Inactive',
    ], fn ($v) => $v !== null);
@endphp

<div class="vx-review">
    @if (blank($name))
        <p class="vx-review-warn">
            Give the item a name on the first step — it can't be saved without one.
        </p>
    @endif

    <dl class="vx-review-grid">
        @foreach ($rows as $label => $value)
            <div>
                <dt>{{ $label }}</dt>
                <dd>{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    @if ($container)
        <p class="vx-review-note">
            You can add the items inside this container after saving, or from the
            Container Scan page.
        </p>
    @endif
</div>
