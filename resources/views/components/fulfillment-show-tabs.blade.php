@props(['show', 'active' => 'orders', 'run' => null])
{{-- One show, one workspace: Orders · Labels · Packing · Activity move between the show's pages in place. --}}
@php
    $run ??= \App\Models\FulfillmentRun::query()->where('show_id', $show->id)->latest('id')->first(['id', 'show_id', 'status']);
    $tabs = array_filter([
        'orders' => \App\Filament\Resources\FulfillmentResource::canAccess() ? ['Orders', 'heroicon-o-document-text', \App\Filament\Resources\FulfillmentResource::getUrl('view', ['record' => $show->id]), null] : null,
        'labels' => \App\Filament\Resources\FulfillmentResource::canAccess() ? ['Labels', 'heroicon-o-printer', \App\Filament\Resources\FulfillmentResource::getUrl('shipping', ['record' => $show->id]), null] : null,
        'packing' => \App\Filament\Pages\FulfillmentStationBoard::canAccess() ? ['Packing', 'heroicon-o-cube', $run ? \App\Filament\Pages\FulfillmentStationBoard::getUrl(['run' => $run->id]) : null, $run ? null : 'Packing has not started'] : null,
        'activity' => \App\Filament\Pages\FulfillmentActivity::canAccess() ? ['Activity', 'heroicon-o-clock', $run ? \App\Filament\Pages\FulfillmentActivity::getUrl(['tab' => 'shows', 'run' => $run->id]) : null, $run ? null : 'No packing activity yet'] : null,
    ]);
@endphp
@if (count($tabs) > 1)
<nav {{ $attributes->class('vxf-showtabs') }} aria-label="{{ $show->title }} workspace">
    <span class="vxf-showtabs__title" title="{{ $show->title }}">{{ $show->title }}@if ($run)<span class="vxf-showtabs__state">{{ \App\Models\FulfillmentRun::statusLabel($run->status) }}</span>@endif</span>
    <span class="vxf-showtabs__links">
        @foreach ($tabs as $key => [$label, $icon, $url, $why])
            @if ($url)
                <a href="{{ $url }}" wire:navigate @class(['vxf-showtabs__tab', 'is-active' => $active === $key]) @if ($active === $key) aria-current="page" @endif><x-dynamic-component :component="$icon" aria-hidden="true" />{{ $label }}</a>
            @else
                <span class="vxf-showtabs__tab is-off" title="{{ $why }}" aria-disabled="true"><x-dynamic-component :component="$icon" aria-hidden="true" />{{ $label }}</span>
            @endif
        @endforeach
    </span>
</nav>
@endif
