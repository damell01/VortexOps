<x-filament-panels::page>
@include('filament.pages.partials.reporting-workspace-styles')
@php
[$from,$to,$label]=$this->range(); $rows=$this->rows(); $all=$this->totals();
$complete=$rows->filter(fn($r)=>$r['incomplete']===[] && ($r['pay_completed'] ?? false));
$pending=$rows->count()-$complete->count();
$final=app(\App\Services\ProfitLossReport::class)->totals($complete);
$losses=app(\App\Services\WhatnotLossReport::class)->rows($from,$to,$channel!==''?(int)$channel:null, $business!==''?(int)$business:null);
@endphp
<div class="vx-report-hub">
    <section class="rh-card rh-toolbar">
        @foreach(['week'=>'This Week','month'=>'This Month','year'=>'This Year'] as $key=>$text)<button type="button" wire:click="setView('{{ $key }}')" class="rh-button {{ $period===$key?'primary':'' }}">{{ $text }}</button>@endforeach
        <b>{{ $label }}</b>
        <label>Business<select wire:model.live="business"><option value="">All businesses</option>@foreach($this->options()['businesses'] as $id=>$name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
        <label>Channel<select wire:model.live="channel"><option value="">All channels</option>@foreach($this->options()['channels'] as $id=>$name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
    </section>
    <div class="rh-grid">
        <section class="rh-card"><span>Gross Sales</span><strong>${{ number_format($all['sales'],2) }}</strong></section>
        <section class="rh-card"><span>Whatnot Earnings + Tips</span><strong>${{ number_format($all['earnings'],2) }}</strong></section>
        <section class="rh-card"><span>Profit After Paid Runs</span><strong>{{ $complete->isEmpty()?'—':'$'.number_format($final['profit'],2) }}</strong><p>{{ $complete->count() }} shows with complete costs and paid runs</p></section>
        <section class="rh-card"><span>Pending Financials</span><strong>{{ $pending }} shows</strong><p>Missing costs, reports or final payouts</p></section>
    </div>
    <div class="rh-two">
    <section class="rh-card"><h2>Whatnot Earnings + Tips Trend</h2><p>Days with shows in this range. Earnings exclude inventory costs and payroll.</p>
    @php $series=$rows->groupBy(fn($r)=>$r['date']?->format($period==='year'?'Y-m':'Y-m-d'))->map(fn($r,$d)=>['label'=>$d,'value'=>$r->sum('earnings')]); $max=max(1,$series->max(fn($v)=>abs($v['value']))); @endphp
    <div class="rh-trend">
        @forelse($series as $point)<div class="rh-trend-item" title="{{ $point['label'] }}: ${{ number_format($point['value'],2) }}"><b>${{ number_format($point['value']) }}</b><div class="rh-trend-bar" style="height:{{ max(4,abs($point['value'])/$max*145) }}px;background:{{ $point['value']<0?'#ef4444':'#8b5cf6' }}"></div><small>{{ \Illuminate\Support\Carbon::parse($point['label'])->format($period==='year'?'M':'M j') }}</small></div>@empty<p>No show earnings in this period.</p>@endforelse
    </div></section>
    <section class="rh-card"><h2>Needs Attention</h2>
        <a class="rh-row" href="{{ \App\Filament\Pages\WhatnotLosses::getUrl(['from'=>$from->toDateString(),'to'=>$to->toDateString(),'channel'=>$channel]) }}"><b>Negative Whatnot Earnings</b><span>{{ $losses->count() }} shows →</span></a>
        <a class="rh-row" href="{{ \App\Filament\Pages\ProfitLoss::getUrl(['period'=>$period,'anchor'=>$anchor,'channel'=>$channel,'business'=>$business,'showFilter'=>'losing']) }}"><b>Business Losses After Paid Runs</b><span>{{ $complete->where('profit','<',0)->count() }} shows →</span></a>
        <a class="rh-row" href="{{ \App\Filament\Pages\ProfitLoss::getUrl(['period'=>$period,'anchor'=>$anchor,'channel'=>$channel,'business'=>$business,'showFilter'=>'pending']) }}"><b>Pending Costs &amp; Payroll</b><span>{{ $pending }} shows →</span></a>
    </section>
    </div>
    <section><h2>Report Library</h2><div class="rh-grid">
        @foreach($this->destinations() as [$title,$description,$url,$icon])<a class="rh-card rh-link" href="{{ $url }}"><x-filament::icon :icon="$icon"/><h2 class="mt-3">{{ $title }}</h2><p>{{ $description }}</p><span class="rh-button mt-4">Open Report →</span></a>@endforeach
    </div></section>
    <p class="rh-note">Incomplete shows are excluded from the profit-after-paid-runs figure. Whatnot estimates may change. This report uses your existing cost and payout calculations.</p>
</div>
</x-filament-panels::page>
