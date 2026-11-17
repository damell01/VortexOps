{{-- "Where's this package?" — a buyer, Whatnot order number or tracking number, answered in place. --}}
<section class="vxf-card vxf-findpkg" aria-labelledby="vxf-findpkg-title" x-data="{ open: true }" x-on:click.outside="open = false">
    <label class="vxf-findpkg__bar" for="vxf-findpkg-input">
        <x-heroicon-o-magnifying-glass aria-hidden="true" />
        <span class="sr-only" id="vxf-findpkg-title">Where's this package?</span>
        <input id="vxf-findpkg-input" type="search" autocomplete="off" spellcheck="false"
            placeholder="Where's this package? Buyer, order # or tracking #"
            wire:model.live.debounce.400ms="packageSearch" x-on:focus="open = true" x-on:keydown.escape="open = false; $el.blur()">
        <span class="vxf-findpkg__spin" wire:loading wire:target="packageSearch" aria-hidden="true"></span>
    </label>
    @php $term = trim($packageSearch); @endphp
    @if ($term !== '')
        <div class="vxf-findpkg__results" x-show="open" x-transition.opacity role="region" aria-live="polite" aria-label="Package search results">
            @if (mb_strlen(trim(ltrim($term, '@#'))) < 3)
                <p class="vxf-empty">Keep typing: at least 3 characters.</p>
            @elseif (! count($this->packageHits))
                <p class="vxf-empty">Nothing matches “{{ $term }}”. Buyer names match from the start; order and tracking numbers in full or from the start.</p>
            @else
                <ul class="vxf-findpkg__list">
                    @foreach ($this->packageHits as $hit)
                        <li class="vxf-findpkg__hit">
                            <span class="vxf-findpkg__letter" aria-hidden="true">{{ $hit['letter'] ?? '·' }}</span>
                            <div class="vxf-findpkg__main">
                                <strong>{{ $hit['buyer'] }}</strong>
                                <span class="vxf-sub">{{ $hit['show'] }}@if ($hit['show_date']) · {{ $hit['show_date'] }}@endif @if ($hit['letter'])· buyer {{ $hit['letter'] }}@endif</span>
                                <span class="vxf-findpkg__meta">
                                    <span class="vxf-pill vxf-pill--{{ $hit['tone'] }}">{{ $hit['state'] }}</span>
                                    @if ($hit['ship_status'])<span class="vxf-pill">{{ $hit['ship_status'] }}</span>@endif
                                    @if ($hit['tracking'])<span class="vxf-sub vxf-num" title="Tracking number">{{ $hit['tracking'] }}</span>@endif
                                    @if ($hit['orders'])<span class="vxf-sub vxf-num" title="Whatnot orders">#{{ implode(', #', array_slice($hit['orders'], 0, 3)) }}@if (count($hit['orders']) > 3) +{{ count($hit['orders']) - 3 }}@endif</span>@endif
                                </span>
                            </div>
                            @if (\App\Filament\Resources\FulfillmentResource::canAccess())
                                <a class="vxf-btn vxf-btn--sm" href="{{ \App\Filament\Resources\FulfillmentResource::getUrl('view', ['record' => $hit['show_id']]) }}">Open show</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</section>
