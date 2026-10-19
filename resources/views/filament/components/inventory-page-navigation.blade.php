@php
    $inventoryLinks = \App\Support\InventoryNavigation::linksForPage($scopes ?? []);
@endphp
@if ($inventoryLinks)
    <nav class="vx-inventory-page-nav" aria-label="Inventory sections">
        @foreach ($inventoryLinks as $link)
            <a href="{{ $link['url'] }}"
               @if (rtrim(url()->current(), '/') === rtrim($link['url'], '/')) aria-current="page" @endif>{{ $link['label'] }}</a>
        @endforeach
        @if (\App\Filament\Pages\InventoryGuide::canAccess())
            <a class="vx-inventory-guide" href="{{ \App\Filament\Pages\InventoryGuide::getUrl(panel: 'admin', parameters: ['tab' => 'tasks']) }}">Inventory guide ↗</a>
        @endif
    </nav>
    <style>
        .vx-inventory-page-nav{display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding:0 32px;margin:0 0 4px}
        .vx-inventory-page-nav a{display:inline-flex;align-items:center;min-height:40px;padding:8px 14px;border:1px solid var(--vx-border,#e3e3ea);border-radius:10px;background:var(--vx-surface,#fff);color:var(--vx-muted,#5a5a68);font-size:13px;font-weight:600;text-decoration:none}
        .vx-inventory-page-nav a:hover,.vx-inventory-page-nav a[aria-current=page]{color:#7c3aed;border-color:#c4b5fd;background:var(--vx-soft-primary,#f1ebfe)}
        .vx-inventory-page-nav a:focus-visible{outline:3px solid #a78bfa;outline-offset:2px}
        .vx-inventory-page-nav .vx-inventory-guide{margin-inline-start:auto;background:transparent;border-color:transparent}
        @media(max-width:1023px){.vx-inventory-page-nav{padding-inline:24px}}
        @media(max-width:639px){.vx-inventory-page-nav{padding-inline:16px;gap:6px}.vx-inventory-page-nav a{min-height:44px;padding:8px 10px}.vx-inventory-page-nav .vx-inventory-guide{margin-inline-start:0}}
    </style>
@endif
