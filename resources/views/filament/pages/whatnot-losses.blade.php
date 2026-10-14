<x-filament-panels::page>
@include('filament.pages.partials.reporting-workspace-styles')
@php $rows = $this->lossRows(); @endphp
<div class="vx-report-hub">
    <section class="rh-card rh-toolbar">
        <label>From<input type="date" wire:model.live="from"></label><label>To<input type="date" wire:model.live="to"></label>
        <label>Channel<select wire:model.live="channel"><option value="">All channels</option>@foreach($this->options()['channels'] as $id=>$name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
        <a class="rh-button" href="{{ \App\Filament\Pages\NotificationSettings::getUrl(['configure'=>'daily_whatnot_losses']) }}">Notification Settings</a>
        {{ $this->sendSummaryAction }}
    </section>
    <div class="rh-grid">
        <section class="rh-card"><span>Negative Shows</span><strong>{{ $rows->count() }}</strong></section>
        <section class="rh-card"><span>Estimated Whatnot Earnings</span><strong style="color:#b91c1c">−${{ number_format(abs($rows->sum('whatnot_net')),2) }}</strong></section>
        <section class="rh-card"><span>Channels Affected</span><strong>{{ $rows->pluck('whatnot_channel_id')->filter()->unique()->count() }}</strong></section>
    </div>
    <p class="rh-note">Whatnot estimates may change as analytics refresh. These losses do not include inventory costs or worker payouts.</p>
    <section class="rh-card">
        @forelse($rows->slice((max(1,$lossPage)-1)*20,20) as $show)
        <a class="rh-row" href="{{ \App\Filament\Resources\ShowResource::getUrl('view',['record'=>$show]) }}">
            <div><b>{{ $show->title }}</b><p>{{ $show->show_date->format('M j, Y') }} · {{ $show->channel?->name }} · {{ $show->streamers->pluck('name')->join(', ') }}</p></div>
            <div><b style="color:#b91c1c">−${{ number_format(abs($show->whatnot_net),2) }}</b><p>Last synced {{ $show->last_analytics_synced_at?->diffForHumans() ?? 'Unknown' }}</p></div>
        </a>
        @empty<p>No negative Whatnot earnings in this range.</p>@endforelse
    </section>
    <div class="rh-toolbar"><span>Page {{ $lossPage }} of {{ max(1,(int)ceil($rows->count()/20)) }}</span><button type="button" class="rh-button" wire:click="$set('lossPage', {{ max(1,$lossPage-1) }})" @disabled($lossPage<=1)>Previous</button><button type="button" class="rh-button" wire:click="$set('lossPage', {{ $lossPage+1 }})" @disabled($lossPage*20 >= $rows->count())>Next</button></div>
</div>
</x-filament-panels::page>
