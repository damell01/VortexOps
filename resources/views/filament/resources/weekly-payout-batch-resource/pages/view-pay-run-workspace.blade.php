<x-filament-panels::page>
@php
    $run = $this->record;
    $run->loadMissing(['payouts.streamer','payouts.show','createdBy','finalizedBy']);
    $payouts = $run->payouts;
    $problems = $run->status === 'draft' ? app(\App\Services\PayRunReadinessService::class)->problems($run) : [];
    $byPerson = $payouts->groupBy('streamer_id');
    $peopleTotal = $byPerson->count();
    $peopleLastPage = max(1, (int) ceil($peopleTotal / $this::PEOPLE_PER_PAGE));
    $peoplePage = min(max(1, $this->peoplePage), $peopleLastPage);
    $visiblePeople = $byPerson->slice(($peoplePage - 1) * $this::PEOPLE_PER_PAGE, $this::PEOPLE_PER_PAGE);
    $streamerTotal = (float)$payouts->filter(fn($p)=>!$p->streamer?->isFulfillment())->sum('calculated_payout');
    $fulfillmentTotal = (float)$payouts->filter(fn($p)=>$p->streamer?->isFulfillment())->sum('calculated_payout');
    $statusLabel = \App\Models\WeeklyPayoutBatch::statusLabels()[$run->status] ?? ucfirst($run->status);
    $statusSteps = ['draft'=>'Review','finalized'=>'Finalized','submitted_to_adp'=>'Submitted','paid'=>'Paid'];
    $statusKeys = array_keys($statusSteps);
    $statusIndex = array_search($run->status,$statusKeys,true);
    $statusIndex = $statusIndex === false ? 0 : $statusIndex;
    $showCount = $payouts->pluck('show_id')->filter()->unique()->count();
    $nextAction = match ($run->status) {
        'draft' => $problems !== [] ? ['label'=>'Resolve blockers','detail'=>count($problems).' issue'.(count($problems)===1?'':'s').' must be cleared before finalizing.','tone'=>'warning','url'=>\App\Filament\Pages\PayrollOverview::getUrl(['workflow'=>'blocked'])] : ['label'=>'Finalize pay run','detail'=>'All readiness checks are clear. Finalize to lock payout amounts.','tone'=>'success','url'=>null],
        'finalized' => ['label'=>'Submit to ADP','detail'=>'Payout amounts are locked. Export the CSV and submit this run to ADP.','tone'=>'primary','url'=>null],
        'submitted_to_adp' => ['label'=>'Mark paid','detail'=>'ADP submission is recorded. Mark the run paid once payment is confirmed.','tone'=>'success','url'=>null],
        'paid' => ['label'=>'Pay run complete','detail'=>'This run is paid and team balances have been updated.','tone'=>'success','url'=>null],
        default => ['label'=>$statusLabel,'detail'=>'Review this pay run.','tone'=>'gray','url'=>null],
    };
@endphp

<div class="vx-payrun-workspace space-y-4">
    <section class="vx-card p-4 vx-payrun-summary">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-[.14em] text-primary-600">Pay Run Workspace</div>
                <h1 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $run->week_start?->format('M j') }} – {{ $run->week_end?->format('M j, Y') }}</h1>
                <div class="mt-2 flex flex-wrap gap-2"><span class="vx-status vx-status--draft {{ $run->status==='paid'?'ready':($run->status==='draft'&&count($problems)?'warn':'run') }}">{{ $statusLabel }}</span><span class="vx-status vx-status--draft">{{ $byPerson->count() }} people</span><span class="vx-status vx-status--draft">{{ $showCount }} shows</span><span class="vx-status vx-status--draft">{{ $payouts->count() }} payout entries</span></div>
            </div>
            <a href="{{ \App\Filament\Pages\PayrollOverview::getUrl() }}" class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold">← Payroll Command Center</a>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-[10px] border border-[var(--vx-divider)] p-4"><label>Total Payroll</label><strong>${{ number_format((float)$run->total_payout,2) }}</strong></div>
            <div class="rounded-[10px] border border-[var(--vx-divider)] p-4"><label>Streamer Pay</label><strong>${{ number_format($streamerTotal,2) }}</strong></div>
            <div class="rounded-[10px] border border-[var(--vx-divider)] p-4"><label>Fulfillment Pay</label><strong>${{ number_format($fulfillmentTotal,2) }}</strong></div>
            <div class="rounded-[10px] border border-[var(--vx-divider)] p-4"><label>Blockers</label><strong class="{{ count($problems) ? 'text-amber-600' : 'text-emerald-600' }}">{{ count($problems) }}</strong></div>
        </div>
    </section>

    <section class="vx-card p-4">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="vx-run-progress">
                @foreach(['draft'=>'Fix & Review','finalized'=>'Finalized','submitted_to_adp'=>'Submitted','paid'=>'Paid'] as $key=>$label)
                    @php $i=array_search($key,$statusKeys,true); @endphp
                    <div class="vx-run-stage {{ $i < $statusIndex ? 'done' : ($i === $statusIndex ? 'current' : '') }}">
                        <span>{{ $i < $statusIndex ? '✓' : $i+1 }}</span><b>{{ $label }}</b>
                    </div>
                @endforeach
            </div>
            <div class="vx-next {{ $nextAction['tone'] }} !mt-0 xl:min-w-[360px]">
                <div><div class="text-[9px] font-bold uppercase tracking-[.12em] opacity-70">Next action</div><div class="mt-1 text-sm font-bold">{{ $nextAction['label'] }}</div><div class="mt-1 text-[11px] opacity-90">{{ $nextAction['detail'] }}</div></div>
                @if($run->status === 'draft' && count($problems))
                    <a href="{{ \App\Filament\Pages\PayrollOverview::getUrl(['workflow'=>'blocked']) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-[var(--vx-border)] px-3 text-xs font-semibold">Fix Issues</a>
                @elseif($run->status === 'draft')
                    <button type="button" wire:click="mountAction('finalize')" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-emerald-600 px-3 text-xs font-semibold text-white">Finalize</button>
                @elseif($run->status === 'finalized')
                    <div class="flex flex-wrap gap-2"><button type="button" wire:click="mountAction('export_adp')" class="vx-btn">Export ADP CSV</button><button type="button" wire:click="mountAction('mark_submitted')" class="vx-btn primary">Mark Submitted</button></div>
                @elseif($run->status === 'submitted_to_adp')
                    <button type="button" wire:click="mountAction('mark_paid')" class="vx-btn success">Mark Paid</button>
                @endif
            </div>
        </div>
    </section>

    <div class="vx-grid">
        <section class="vx-card p-5">
            <div class="flex items-start justify-between gap-3"><div><h3>People in This Pay Run</h3><div class="mt-1 text-xs text-[var(--vx-muted)]">Person total first. Expand only when you need the lines that built it.</div></div><span class="vx-status vx-status--draft">{{ $byPerson->count() }} people</span></div>
            @forelse($visiblePeople as $streamerId=>$rows)
                @php $person=$rows->first()->streamer; $personTotal=(float)$rows->sum('calculated_payout'); $hours=(float)$rows->sum('hours_worked'); $labels=(int)$rows->sum('label_count'); $pwe=(int)$rows->sum('pwe_count'); @endphp
                <details class="vx-person">
                    <summary class="vx-person-head cursor-pointer list-none">
                        <div><div class="flex flex-wrap items-center gap-2"><div class="vx-value">{{ $person?->name ?? 'Unassigned team member' }}</div><span class="vx-status vx-status--draft {{ $person?->isFulfillment() ? 'run' : 'ready' }}">{{ $person?->isFulfillment() ? 'Fulfillment' : 'Streamer' }}</span></div><div class="vx-meta">{{ $rows->count() }} payout line{{ $rows->count()===1?'':'s' }}</div></div>
                        <div class="vx-num"><div class="vx-meta">Hours</div><div class="vx-value">{{ number_format($hours,2) }}</div></div>
                        <div class="vx-num"><div class="vx-meta">Labels / PWE</div><div class="vx-value">{{ $labels }} / {{ $pwe }}</div></div>
                        <div class="vx-num"><div class="vx-meta">Shows</div><div class="vx-value">{{ $rows->pluck('show_id')->filter()->unique()->count() }}</div></div>
                        <div class="vx-num"><div class="vx-meta">Payout</div><div class="vx-value text-emerald-600">${{ number_format($personTotal,2) }}</div></div>
                        <div class="text-gray-400">⌄</div>
                    </summary>
                    <div class="vx-breakdown">
                        @foreach($rows as $payout)<div class="vx-payrow"><div><div class="vx-value truncate">{{ $payout->show?->title ?? 'General payroll line' }}</div><div class="vx-meta">{{ \App\Models\Streamer::payoutTypeLabels()[$payout->payout_type] ?? ucfirst(str_replace('_',' ',$payout->payout_type ?? '')) }}</div></div><div class="vx-num"><span class="vx-meta">Hours</span><br>{{ number_format((float)$payout->hours_worked,2) }}</div><div class="vx-num"><span class="vx-meta">Shipments</span><br>{{ number_format((int)$payout->shipments_count) }}</div><div class="vx-num"><span class="vx-meta">Labels</span><br>{{ number_format((int)$payout->label_count) }}</div><div class="vx-num"><span class="vx-meta">PWE</span><br>{{ number_format((int)$payout->pwe_count) }}</div><div class="vx-num"><strong>${{ number_format((float)$payout->calculated_payout,2) }}</strong></div></div>@endforeach
                    </div>
                </details>
            @empty<div class="py-10 text-center text-sm text-gray-500">No payout entries are attached to this run yet.</div>@endforelse
            @if($peopleLastPage > 1)
                <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-3 dark:border-gray-800">
                    <button type="button" wire:click="previousPeoplePage" @disabled($peoplePage <= 1) class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold disabled:cursor-not-allowed disabled:opacity-40">← Previous</button>
                    <div class="text-[11px] font-semibold text-gray-500">People {{ (($peoplePage-1)*$this::PEOPLE_PER_PAGE)+1 }}–{{ min($peoplePage*$this::PEOPLE_PER_PAGE,$peopleTotal) }} of {{ $peopleTotal }}</div>
                    <button type="button" wire:click="nextPeoplePage({{ $peopleLastPage }})" @disabled($peoplePage >= $peopleLastPage) class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold disabled:cursor-not-allowed disabled:opacity-40">Next →</button>
                </div>
            @endif
        </section>

        <aside class="vx-payrun-side space-y-3">
            <section class="vx-card p-5"><div class="flex items-center justify-between gap-3"><div><h3>Readiness</h3><div class="mt-1 text-xs text-[var(--vx-muted)]">What prevents this run from moving forward.</div></div><span class="vx-status vx-status--draft {{ count($problems)?'warn':'ready' }}">{{ count($problems) }} issue{{ count($problems)===1?'':'s' }}</span></div><div class="mt-3">@forelse($problems as $problem)<div class="vx-alert">{{ $problem }}</div>@empty<div class="vx-ok">✓ This pay run has no readiness blockers.</div>@endforelse</div></section>
            <section class="vx-card p-5"><h3>Run Total</h3><div class="vx-money-card mt-3"><span class="text-[9px] font-bold uppercase tracking-wide text-blue-600 dark:text-blue-300">Total payroll</span><strong>${{ number_format((float)$run->total_payout,2) }}</strong><div class="mt-3 grid grid-cols-2 gap-2 text-xs"><div><span class="text-blue-600/70 dark:text-blue-300/70">Streamer</span><div class="font-bold text-blue-900 dark:text-blue-100">${{ number_format($streamerTotal,2) }}</div></div><div><span class="text-blue-600/70 dark:text-blue-300/70">Fulfillment</span><div class="font-bold text-blue-900 dark:text-blue-100">${{ number_format($fulfillmentTotal,2) }}</div></div></div></div></section>
            <section class="vx-card p-5"><details><summary class="cursor-pointer list-none"><div class="flex items-center justify-between"><div><h3>Run Details</h3><div class="mt-1 text-xs text-[var(--vx-muted)]">Audit and administrative details.</div></div><span class="text-xs font-bold text-primary-600">View</span></div></summary><div class="mt-3">@foreach([['Status',$statusLabel],['Created by',$run->createdBy?->name],['Finalized by',$run->finalizedBy?->name],['Finalized at',$run->finalized_at?->format('M j, Y g:i A')],['Notes',$run->notes]] as [$label,$value])<div class="vx-fin"><span class="text-gray-500">{{ $label }}</span><strong class="text-right">{{ $value ?: '—' }}</strong></div>@endforeach</div></details></section>
        </aside>
    </div>
</div>
</x-filament-panels::page>