<x-filament-panels::page>
@php
    $run = $this->record;
    $run->loadMissing(['payouts.streamer','payouts.show','createdBy','finalizedBy']);
    $payouts = $run->payouts;
    $problems = $run->status === 'draft' ? app(\App\Services\PayRunReadinessService::class)->problems($run) : [];
    $allPeople = $payouts->groupBy('streamer_id');
    $byPerson = $allPeople;
    $peopleSearch = trim($this->peopleSearch);
    if ($peopleSearch !== '') {
        $byPerson = $byPerson->filter(fn($rows) => str_contains(strtolower((string)($rows->first()?->streamer?->name ?? '')), strtolower($peopleSearch)));
    }
    // Largest payouts first: that is the order a reviewer checks them in.
    $byPerson = $byPerson->sortByDesc(fn ($rows) => (float) $rows->sum('calculated_payout'));
    $peopleTotal = $byPerson->count();
    $peopleLastPage = max(1, (int) ceil($peopleTotal / $this::PEOPLE_PER_PAGE));
    $peoplePage = min(max(1, $this->peoplePage), $peopleLastPage);
    $visiblePeople = $byPerson->slice(($peoplePage - 1) * $this::PEOPLE_PER_PAGE, $this::PEOPLE_PER_PAGE);
    $streamerTotal = (float)$payouts->filter(fn($p)=>!$p->streamer?->isFulfillment())->sum('calculated_payout');
    $fulfillmentTotal = (float)$payouts->filter(fn($p)=>$p->streamer?->isFulfillment())->sum('calculated_payout');
    $statusLabel = \App\Models\WeeklyPayoutBatch::statusLabels()[$run->status] ?? ucfirst($run->status);
    $statusSteps = ['draft'=>'Review','finalized'=>'Finalized','submitted_to_adp'=>'Sent to ADP','paid'=>'Paid'];
    $statusKeys = array_keys($statusSteps);
    $statusIndex = array_search($run->status,$statusKeys,true);
    $statusIndex = $statusIndex === false ? 0 : $statusIndex;
    $showCount = $payouts->pluck('show_id')->filter()->unique()->count();
    $blockedUrl = \App\Filament\Pages\PayrollOverview::getUrl(['workflow'=>'blocked']);
    $nextAction = match ($run->status) {
        'draft' => $problems !== []
            ? ['title'=>'Resolve '.count($problems).' '.\Illuminate\Support\Str::plural('blocker', count($problems)),'detail'=>'Clear these before the run can be finalized.','tone'=>'warn']
            : ['title'=>'Ready to finalize','detail'=>'All readiness checks are clear. Finalizing locks every payout amount.','tone'=>'ok'],
        'finalized' => ['title'=>'Send to ADP','detail'=>'Amounts are locked. Export the CSV, submit it in ADP, then mark it submitted.','tone'=>'info'],
        'submitted_to_adp' => ['title'=>'Confirm payment','detail'=>'Mark the run paid once ADP confirms the payments went out.','tone'=>'info'],
        'paid' => ['title'=>'Pay run complete','detail'=>'This run is paid and team balances have been updated.','tone'=>'ok'],
        default => ['title'=>$statusLabel,'detail'=>'Review this pay run.','tone'=>'info'],
    };
    $runTone = ['paid'=>'ok','finalized'=>'info','submitted_to_adp'=>'info','draft'=>count($problems) ? 'warn' : 'accent'][$run->status] ?? '';
    $money = fn ($v) => '$' . number_format((float) $v, 2);
@endphp

<div class="vxw" data-vx-page="pay-run">
    <section class="vxw-card">
        <div class="vxw-card-body grid gap-5">
            <div class="vxw-hero">
                <div>
                    <div class="vxw-eyebrow">Pay run</div>
                    <h2 class="vxw-h1">{{ $run->week_start?->format('M j') }} – {{ $run->week_end?->format('M j, Y') }}</h2>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="vxw-pill vxw-pill--dot vxw-pill--{{ $runTone }}">{{ $statusLabel }}</span>
                        <span class="vxw-meta">{{ $allPeople->count() }} {{ \Illuminate\Support\Str::plural('person', $allPeople->count()) }} · {{ $showCount }} {{ \Illuminate\Support\Str::plural('show', $showCount) }} · {{ $payouts->count() }} payout {{ \Illuminate\Support\Str::plural('line', $payouts->count()) }}</span>
                    </div>
                </div>
                <div class="vxw-hero-actions" style="width:auto">
                    <a href="{{ \App\Filament\Pages\PayrollOverview::getUrl() }}" class="vxw-btn vxw-btn--ghost vxw-btn--sm"><x-filament::icon icon="heroicon-m-arrow-left" /> Payroll</a>
                    <a href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('index') }}" class="vxw-btn vxw-btn--ghost vxw-btn--sm">History</a>
                </div>
            </div>

            <ol class="vxw-stages" aria-label="Pay run progress">
                @foreach($statusSteps as $key => $label)
                    @php $i = array_search($key, $statusKeys, true); @endphp
                    <li @class(['vxw-stage', 'is-done' => $i < $statusIndex || $run->status === 'paid', 'is-current' => $i === $statusIndex && $run->status !== 'paid']) @if($i === $statusIndex) aria-current="step" @endif>
                        <span class="vxw-stage-dot">@if($i < $statusIndex || $run->status === 'paid')<x-filament::icon icon="heroicon-m-check" />@else{{ $i + 1 }}@endif</span>
                        <span class="vxw-stage-label">{{ $label }}</span>
                    </li>
                @endforeach
            </ol>

            {{-- The single next step, with the button that does it. --}}
            <div class="vxw-alert vxw-alert--{{ $nextAction['tone'] }}" style="align-items:center;flex-wrap:wrap">
                <x-filament::icon :icon="$nextAction['tone'] === 'warn' ? 'heroicon-o-exclamation-triangle' : ($nextAction['tone'] === 'ok' ? 'heroicon-o-check-circle' : 'heroicon-o-arrow-right-circle')" />
                <div class="flex-1" style="min-width:200px">
                    <div class="font-semibold">{{ $nextAction['title'] }}</div>
                    <div class="vxw-meta">{{ $nextAction['detail'] }}</div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($run->status === 'draft' && count($problems))
                        <a href="{{ $blockedUrl }}" class="vxw-btn vxw-btn--sm">Fix blocked shows</a>
                    @elseif($run->status === 'draft')
                        <button type="button" wire:click="mountAction('finalize')" class="vxw-btn vxw-btn--success vxw-btn--sm"><x-filament::icon icon="heroicon-m-lock-closed" /> Finalize</button>
                    @elseif($run->status === 'finalized')
                        <button type="button" wire:click="mountAction('export_adp')" class="vxw-btn vxw-btn--sm"><x-filament::icon icon="heroicon-m-arrow-down-tray" /> Export ADP CSV</button>
                        <button type="button" wire:click="mountAction('mark_submitted')" class="vxw-btn vxw-btn--primary vxw-btn--sm">Mark submitted</button>
                    @elseif($run->status === 'submitted_to_adp')
                        <button type="button" wire:click="mountAction('mark_paid')" class="vxw-btn vxw-btn--success vxw-btn--sm">Mark paid</button>
                    @endif
                </div>
            </div>
        </div>
        <div class="vxw-stats">
            <div class="vxw-stat"><div class="vxw-stat-label">Total payroll</div><div class="vxw-stat-value">{{ $money($run->total_payout) }}</div></div>
            <div class="vxw-stat"><div class="vxw-stat-label">Streamer pay</div><div class="vxw-stat-value">{{ $money($streamerTotal) }}</div></div>
            <div class="vxw-stat"><div class="vxw-stat-label">Fulfillment pay</div><div class="vxw-stat-value">{{ $money($fulfillmentTotal) }}</div></div>
            <div class="vxw-stat"><div class="vxw-stat-label">Blockers</div><div class="vxw-stat-value {{ count($problems) ? 'vxw-tone-warn' : 'vxw-tone-ok' }}">{{ count($problems) }}</div></div>
        </div>
    </section>

    <div class="vxw-split">
        <section class="vxw-card">
            <div class="vxw-card-head">
                <div>
                    <h3 class="vxw-h2">People in this run</h3>
                    <p class="vxw-sub">Largest payouts first. Open a person to see the show lines behind their total.</p>
                </div>
                <div class="vxw-search w-full sm:w-56">
                    <x-filament::icon icon="heroicon-m-magnifying-glass" />
                    <input type="search" wire:model.live.debounce.300ms="peopleSearch" placeholder="Search people" class="vxw-input" aria-label="Search people in this pay run">
                </div>
            </div>

            <div class="vxw-rows" wire:loading.class="opacity-60" wire:target="peopleSearch,previousPeoplePage,nextPeoplePage" style="transition:opacity 150ms">
                @forelse($visiblePeople as $streamerId => $rows)
                    @php
                        $person = $rows->first()->streamer;
                        $personTotal = (float) $rows->sum('calculated_payout');
                        $hours = (float) $rows->sum('hours_worked');
                        $labels = (int) $rows->sum('label_count');
                        $pwe = (int) $rows->sum('pwe_count');
                        $personShows = $rows->pluck('show_id')->filter()->unique()->count();
                    @endphp
                    <details class="vxw-disclose" wire:key="pay-person-{{ $streamerId ?: 'none' }}" style="border-top:1px solid var(--vxw-border)">
                        <summary class="vxw-row" style="border-top:0">
                            <div class="vxw-row-main">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="vxw-row-title" style="white-space:normal">{{ $person?->name ?? 'Unassigned team member' }}</span>
                                    <span class="vxw-pill {{ $person?->isFulfillment() ? 'vxw-pill--info' : 'vxw-pill--accent' }}" style="height:20px;font-size:11px">{{ $person?->isFulfillment() ? 'Fulfillment' : 'Streamer' }}</span>
                                </div>
                                <div class="vxw-row-line">
                                    <span>{{ $personShows }} {{ \Illuminate\Support\Str::plural('show', $personShows) }}</span>
                                    <span>{{ number_format($hours, 2) }} hrs</span>
                                    @if($labels || $pwe)<span>{{ $labels }} labels · {{ $pwe }} PWE</span>@endif
                                </div>
                            </div>
                            <div class="vxw-row-side">
                                <div class="vxw-fig"><div class="vxw-fig-label">Payout</div><div class="vxw-fig-value" style="font-size:15px">{{ $money($personTotal) }}</div></div>
                                <x-filament::icon icon="heroicon-m-chevron-down" class="vxw-chevron" />
                            </div>
                        </summary>
                        <div class="vxw-disclose-body">
                            @foreach($rows as $payout)
                                <div class="vxw-subrow">
                                    <div>
                                        <div class="truncate font-semibold text-[var(--vxw-text)]">{{ $payout->show?->title ?? 'General payroll line' }}</div>
                                        <div class="vxw-meta">{{ \App\Models\Streamer::payoutTypeLabels()[$payout->payout_type] ?? ucfirst(str_replace('_',' ',$payout->payout_type ?? '')) }}@if($payout->show?->show_date) · {{ $payout->show->show_date->format('M j') }}@endif</div>
                                    </div>
                                    <div><div class="vxw-fig-label">Hours</div><div class="vxw-num">{{ number_format((float)$payout->hours_worked, 2) }}</div></div>
                                    <div><div class="vxw-fig-label">Shipments</div><div class="vxw-num">{{ number_format((int)$payout->shipments_count) }}</div></div>
                                    <div><div class="vxw-fig-label">Labels</div><div class="vxw-num">{{ number_format((int)$payout->label_count) }}</div></div>
                                    <div><div class="vxw-fig-label">PWE</div><div class="vxw-num">{{ number_format((int)$payout->pwe_count) }}</div></div>
                                    <div class="text-right max-[900px]:text-left"><div class="vxw-fig-label">Amount</div><div class="vxw-fig-value">{{ $money($payout->calculated_payout) }}</div></div>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @empty
                    <div class="vxw-empty">
                        <x-filament::icon icon="heroicon-o-users" />
                        <div class="vxw-empty-text">{{ $peopleSearch !== '' ? 'No people match your search.' : 'No payout lines are attached to this run yet.' }}</div>
                    </div>
                @endforelse
            </div>

            @if($peopleLastPage > 1)
                <div class="vxw-card-foot">
                    <span class="vxw-meta">{{ (($peoplePage-1)*$this::PEOPLE_PER_PAGE)+1 }}–{{ min($peoplePage*$this::PEOPLE_PER_PAGE,$peopleTotal) }} of {{ $peopleTotal }} people</span>
                    <div class="vxw-pager">
                        <button type="button" class="vxw-page" wire:click="previousPeoplePage" @disabled($peoplePage <= 1) aria-label="Previous page">‹</button>
                        <span class="vxw-meta px-2">{{ $peoplePage }} / {{ $peopleLastPage }}</span>
                        <button type="button" class="vxw-page" wire:click="nextPeoplePage({{ $peopleLastPage }})" @disabled($peoplePage >= $peopleLastPage) aria-label="Next page">›</button>
                    </div>
                </div>
            @endif
        </section>

        <aside class="vxw-aside">
            <section class="vxw-card">
                <div class="vxw-card-head">
                    <h3 class="vxw-h2">Readiness</h3>
                    <span class="vxw-pill vxw-pill--{{ count($problems) ? 'warn' : 'ok' }}">{{ count($problems) }} {{ \Illuminate\Support\Str::plural('issue', count($problems)) }}</span>
                </div>
                <div class="vxw-card-body">
                    @if($problems)
                        <ul class="vxw-issues">@foreach($problems as $problem)<li>{{ $problem }}</li>@endforeach</ul>
                        <a href="{{ $blockedUrl }}" class="vxw-btn vxw-btn--sm vxw-btn--block mt-3">Open blocked shows</a>
                    @else
                        <div class="vxw-meta vxw-tone-ok">{{ $run->status === 'draft' ? 'Nothing blocks this run.' : 'Checks passed when this run was finalized.' }}</div>
                    @endif
                </div>
            </section>
            <section class="vxw-card">
                <details class="vxw-disclose">
                    <summary class="vxw-card-head" style="border-bottom:0">
                        <h3 class="vxw-h2">Run details</h3>
                        <x-filament::icon icon="heroicon-m-chevron-down" class="vxw-chevron" />
                    </summary>
                    <div class="vxw-card-body" style="padding-top:0">
                        <dl class="vxw-dl">
                            @foreach([['Status',$statusLabel],['Created by',$run->createdBy?->name],['Finalized by',$run->finalizedBy?->name],['Finalized at',$run->finalized_at?->format('M j, Y g:i A')],['Notes',$run->notes]] as [$label,$value])
                                <div><dt>{{ $label }}</dt><dd>{{ $value ?: '—' }}</dd></div>
                            @endforeach
                        </dl>
                    </div>
                </details>
            </section>
        </aside>
    </div>
</div>
</x-filament-panels::page>
