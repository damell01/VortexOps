<x-filament-panels::page>
@php
    $streamer = $this->record;
    $streamer->loadMissing(['channel','inventoryLocations']);
    $recentShows = $streamer->shows()->with('channel')->orderByDesc('show_date')->limit(8)->get();
    $upcomingShows = $streamer->shows()->with('channel')->whereDate('show_date','>=',today())->whereNotIn('shows.status',['cancelled'])->orderBy('show_date')->limit(5)->get();
    $recentPayouts = $streamer->payouts()->with(['show','batch'])->latest()->limit(8)->get();
    $comp = $streamer->effectiveCompensation();
    $effective = $comp['effective'] ?? [];
    $score = $streamer->scorecard();
    $due = (float)$streamer->total_earnings_due;
    $paid = (float)$streamer->total_earnings_paid;
    $balance = $streamer->outstandingBalance();
@endphp
<style>
.fi-header{display:none!important}
.vx-person{max-width:1380px;margin:0 auto;display:grid;gap:14px}.vx-card{border:1px solid #e5e7eb;background:#fff;border-radius:16px;box-shadow:0 1px 2px rgba(15,23,42,.04)}.dark .vx-card{border-color:#263248;background:#101827}.vx-hero{padding:18px 20px}.vx-avatar{width:52px;height:52px;border-radius:16px;display:grid;place-items:center;background:#ede9fe;color:#6d28d9;font-weight:850;font-size:18px}.dark .vx-avatar{background:#2e1065;color:#c4b5fd}.vx-chip{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:750;background:#f3f4f6;color:#4b5563}.dark .vx-chip{background:#1f2937;color:#d1d5db}.vx-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));border-top:1px solid #eef0f3;margin-top:16px}.dark .vx-kpis{border-color:#263248}.vx-kpi{padding:14px 16px;border-right:1px solid #eef0f3}.dark .vx-kpi{border-color:#263248}.vx-kpi:last-child{border-right:0}.vx-kpi label{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.06em;font-weight:800;color:#9ca3af}.vx-kpi strong{display:block;margin-top:4px;font-size:20px;color:#111827}.dark .vx-kpi strong{color:#fff}.vx-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(300px,.75fr);gap:14px}.vx-section{padding:18px}.vx-section h3{font-size:14px;font-weight:800;color:#111827}.dark .vx-section h3{color:#fff}.vx-sub{font-size:11px;color:#6b7280;margin-top:2px}.vx-row{display:flex;align-items:center;gap:12px;padding:12px 0;border-top:1px solid #f1f3f5}.dark .vx-row{border-color:#1f2937}.vx-value{font-size:13px;font-weight:750;color:#111827}.dark .vx-value{color:#fff}.vx-meta{font-size:11px;color:#6b7280;margin-top:2px}.vx-date{width:44px;flex:none;text-align:center;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden}.dark .vx-date{border-color:#334155}.vx-date b{display:block;background:#7c3aed;color:#fff;font-size:9px;text-transform:uppercase;padding:2px}.vx-date span{display:block;padding:5px 0;font-size:16px;font-weight:800}.vx-fin{display:grid;grid-template-columns:1fr auto;gap:8px;padding:8px 0;font-size:12px;border-top:1px solid #f3f4f6}.dark .vx-fin{border-color:#1f2937}.vx-link{display:inline-flex;align-items:center;justify-content:center;min-height:38px;border-radius:10px;border:1px solid #d1d5db;padding:7px 10px;font-size:11px;font-weight:750;color:#374151;background:#fff}.dark .vx-link{border-color:#475569;color:#e5e7eb;background:#111827}.vx-link.primary{background:#7c3aed;border-color:#7c3aed;color:#fff}.vx-actions{display:flex;gap:8px;flex-wrap:wrap}.vx-rate-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px}.vx-rate{border-radius:12px;background:#f8fafc;padding:11px}.dark .vx-rate{background:#1f2937}.vx-rate label{display:block;font-size:9px;text-transform:uppercase;letter-spacing:.05em;font-weight:800;color:#94a3b8}.vx-rate strong{display:block;margin-top:3px;font-size:13px}.vx-empty{padding:24px 0;text-align:center;font-size:12px;color:#9ca3af}
@media(max-width:900px){.vx-grid{grid-template-columns:1fr}.vx-kpis{grid-template-columns:repeat(2,1fr)}.vx-kpi:nth-child(2n){border-right:0}}
@media(max-width:640px){.vx-person{gap:10px}.vx-hero,.vx-section{padding:14px}.vx-kpis{margin-inline:-14px;margin-bottom:-14px}.vx-kpi strong{font-size:17px}.vx-actions{display:grid;grid-template-columns:1fr 1fr}.vx-link{min-height:44px}.vx-rate-grid{grid-template-columns:1fr 1fr}}
</style>
<div class="vx-person">
    <section class="vx-card vx-hero">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 items-center gap-3">
                <div class="vx-avatar">{{ strtoupper(substr($streamer->name ?: 'T',0,2)) }}</div>
                <div class="min-w-0"><h1 class="truncate text-2xl font-bold text-gray-950 dark:text-white">{{ $streamer->name }}</h1><div class="mt-1 flex flex-wrap gap-2"><span class="vx-chip">{{ \App\Models\Streamer::memberTypeLabels()[$streamer->member_type ?? 'streamer'] ?? ucfirst($streamer->member_type ?? 'streamer') }}</span><span class="vx-chip">{{ \App\Models\Streamer::statusLabels()[$streamer->status] ?? ucfirst($streamer->status) }}</span>@if($streamer->channel)<span class="vx-chip">{{ $streamer->channel->name }}</span>@endif</div></div>
            </div>
            <div class="vx-actions"><button type="button" class="vx-link primary" wire:click="mountAction('allocate_to_pool')">Allocate Inventory</button><a class="vx-link" href="{{ \App\Filament\Resources\StreamerResource::getUrl('edit',['record'=>$streamer]) }}">Edit Profile</a><a class="vx-link" href="{{ \App\Filament\Pages\StreamerStatement::getUrl(['streamer'=>$streamer->id]) }}">Statement</a></div>
        </div>
        <div class="vx-kpis">
            <div class="vx-kpi"><label>Balance Due</label><strong>${{ number_format($balance,2) }}</strong></div>
            <div class="vx-kpi"><label>Total Earned</label><strong>${{ number_format($due,2) }}</strong></div>
            <div class="vx-kpi"><label>Total Paid</label><strong>${{ number_format($paid,2) }}</strong></div>
            <div class="vx-kpi"><label>Shows</label><strong>{{ number_format($score['shows'] ?? 0) }}</strong></div>
            <div class="vx-kpi"><label>Show Sales</label><strong>${{ number_format((float)($score['gross'] ?? 0),0) }}</strong></div>
        </div>
    </section>

    <div class="vx-grid">
        <div class="space-y-3">
            <section class="vx-card vx-section">
                <div class="flex items-start justify-between gap-3"><div><h3>Upcoming Shows</h3><div class="vx-sub">Next shows assigned to this team member.</div></div><span class="vx-chip">{{ $upcomingShows->count() }}</span></div>
                @forelse($upcomingShows as $show)<a href="{{ \App\Filament\Resources\ShowResource::getUrl('view',['record'=>$show]) }}" class="vx-row"><div class="vx-date"><b>{{ $show->show_date?->format('M') }}</b><span>{{ $show->show_date?->format('j') }}</span></div><div class="min-w-0 flex-1"><div class="vx-value truncate">{{ $show->title }}</div><div class="vx-meta">{{ $show->start_time?->format('g:i A') ?? 'Time not set' }}{{ $show->channel ? ' · '.$show->channel->name : '' }}</div></div><span class="text-gray-300">›</span></a>@empty<div class="vx-empty">No upcoming shows assigned.</div>@endforelse
            </section>
            <section class="vx-card vx-section">
                <div class="flex items-start justify-between gap-3"><div><h3>Recent Shows</h3><div class="vx-sub">Sales and results from recent assigned shows.</div></div></div>
                @forelse($recentShows as $show)<a href="{{ \App\Filament\Resources\ShowResource::getUrl('view',['record'=>$show]) }}" class="vx-row"><div class="min-w-0 flex-1"><div class="vx-value truncate">{{ $show->title }}</div><div class="vx-meta">{{ $show->show_date?->format('M j, Y') }} · {{ ucfirst(str_replace('_',' ',$show->status)) }}</div></div><div class="text-right"><div class="vx-value">${{ number_format((float)$show->gross_revenue,2) }}</div><div class="vx-meta">{{ number_format((int)$show->units_sold) }} units</div></div></a>@empty<div class="vx-empty">No show history yet.</div>@endforelse
            </section>
        </div>

        <aside class="space-y-3">
            <section class="vx-card vx-section">
                <h3>Payment Structure</h3><div class="vx-sub">Effective rates currently used for calculations.</div>
                <div class="mt-3"><span class="vx-chip">{{ \App\Models\Streamer::payoutTypeLabels()[$comp['structure'] ?? $streamer->payout_type] ?? ucfirst(str_replace('_',' ',$comp['structure'] ?? $streamer->payout_type ?? 'Not set')) }}</span></div>
                <div class="vx-rate-grid">
                    @foreach([['Hourly',$effective['hourly_rate'] ?? $streamer->hourly_rate,'$'],['Profit Share',$effective['payout_percentage'] ?? $streamer->payout_percentage,'%'],['PWE',$effective['pwe_rate'] ?? $streamer->pwe_rate,'$'],['Label',$effective['label_rate'] ?? $streamer->label_rate,'$']] as [$label,$value,$prefix])
                        <div class="vx-rate"><label>{{ $label }}</label><strong>{{ $value === null ? '—' : ($prefix === '%' ? number_format((float)$value,2).'%' : '$'.number_format((float)$value,2)) }}</strong></div>
                    @endforeach
                </div>
                @if(!empty($comp['overrides']))<div class="mt-3 text-xs font-semibold text-amber-600">Individual rate overrides are active.</div>@endif
            </section>
            <section class="vx-card vx-section">
                <h3>Recent Payouts</h3><div class="vx-sub">Latest calculated earnings and pay-run status.</div>
                @forelse($recentPayouts as $payout)<div class="vx-fin"><div><div class="vx-value">{{ $payout->show?->title ?? 'General payout' }}</div><div class="vx-meta">{{ $payout->batch ? $payout->batch->week_start->format('M j').'–'.$payout->batch->week_end->format('M j') : ucfirst($payout->status) }}</div></div><strong>${{ number_format((float)$payout->calculated_payout,2) }}</strong></div>@empty<div class="vx-empty">No payout history yet.</div>@endforelse
            </section>
            <section class="vx-card vx-section"><h3>Contact & Account</h3>@foreach([['Email',$streamer->email],['Phone',$streamer->phone],['ADP ID',$streamer->adp_employee_id],['Cadence',\App\Models\Streamer::payoutCadenceLabels()[$streamer->payout_cadence] ?? $streamer->payout_cadence]] as [$label,$value])<div class="vx-fin"><span class="text-gray-500">{{ $label }}</span><strong>{{ $value ?: '—' }}</strong></div>@endforeach</section>
        </aside>
    </div>
</div>
</x-filament-panels::page>