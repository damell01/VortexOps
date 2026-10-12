{{-- Channel pills above the calendar: click one to hide or show its shows,
     "only" to show just that channel. Hidden pills stay so they can come back. --}}
@php $legend = $this->channelLegend(); $anyHidden = $legend->contains('hidden', true); @endphp
@if($legend->isNotEmpty())
    <div class="vx-legend" role="group" aria-label="Channels to show">
        @foreach($legend as $c)
            <span class="vx-ctog" wire:key="ctog-{{ $c['key'] }}">
                <button type="button" wire:click="toggleChannel('{{ $c['key'] }}')" @class(['vx-chan', 'off' => $c['hidden']]) style="--c:{{ $c['color'] }}" aria-pressed="{{ $c['hidden'] ? 'false' : 'true' }}" title="{{ $c['hidden'] ? 'Show' : 'Hide' }} {{ $c['name'] }}">{{ $c['name'] }} <span class="n">{{ $c['count'] }}</span></button>
                @if($legend->count() > 1)<button type="button" class="vx-only" wire:click="onlyChannel('{{ $c['key'] }}')" aria-label="Show only {{ $c['name'] }}">only</button>@endif
            </span>
        @endforeach
        @if($anyHidden)<button type="button" class="vx-showall" wire:click="showAllChannels">Show all channels</button>@endif
        <span class="vx-legend-hint">Click a channel to hide or show it</span>
    </div>
@endif
