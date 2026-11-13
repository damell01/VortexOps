{{--
    A list page's tabs as one row of quick-filter chips, for list pages with
    their own view (which leave out Filament's tab bar). Uses getTabs().
--}}
@php
    $tabs = method_exists($this, 'getCachedTabs') ? $this->getCachedTabs() : [];
    $active = (string) ($this->activeTab ?? array_key_first($tabs));
@endphp
@if (count($tabs) > 1)
    <nav class="vx-qtabs" aria-label="Quick filters">
        @foreach ($tabs as $key => $tab)
            @php $badge = $tab->getBadge(); @endphp
            <button type="button" wire:click="$set('activeTab', @js((string) $key))" @class(['vx-qtab', 'is-on' => $active === (string) $key]) aria-pressed="{{ $active === (string) $key ? 'true' : 'false' }}">
                {{ $tab->getLabel() }}@if ($badge !== null && $badge !== '')<span>{{ $badge }}</span>@endif
            </button>
        @endforeach
    </nav>
@endif
