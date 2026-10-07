{{-- Filters live behind the Filters button; the calendar navigation sets the date range. --}}
@php $mobile = $mobile ?? false; @endphp
<div class="vx-filters" @unless($mobile) x-show="filters" x-cloak @endunless @if($mobile) style="grid-template-columns:1fr;border-bottom:0" @endif>
    <label>Search<input type="search" wire:model.live.debounce.400ms="searchQuery" placeholder="Title, notes or Whatnot ID…"></label>
    <label>Status<select wire:model.live="filterStatus"><option value="all">All statuses</option>@foreach(\App\Models\Show::statusLabels() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></label>
    <label>Channel<select wire:model.live="filterChannel"><option value="">All channels</option>@foreach($this->channels as $c)<option value="{{ $c->id }}">{{ $c->display_title ?: $c->name }}</option>@endforeach</select></label>
    @if(auth()->user()?->isAdmin())
        <label>Streamer<select wire:model.live="filterStreamer"><option value="">All streamers</option>@foreach($this->streamers as $st)<option value="{{ $st->id }}">{{ $st->name }}</option>@endforeach</select></label>
    @else
        <span></span>
    @endif
    <button type="button" class="vx-btn" wire:click="clearFilters" @disabled(! $this->activeFilterCount())>Reset</button>
</div>
