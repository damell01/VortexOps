<x-filament-panels::page>
@php
    $logs = $this->logs();
    $stats = $this->stats();
    $opened = $this->opened();
    $statusLabels = \App\Models\EmailLog::STATUSES;
@endphp
<style>
.vx-el{width:100%;--line:#e5e7eb;--soft:#f8fafc;--muted:#64748b;--p:var(--primary-600,#7c3aed);max-width:1180px;margin:0 auto;display:grid;gap:14px}
.dark .vx-el{--line:#253247;--soft:#111c2f;--muted:#94a3b8}
.vx-el .card{background:#fff;border:1px solid var(--line);border-radius:16px}
.dark .vx-el .card{background:#0f172a}
.vx-el .stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.vx-el .stat{padding:14px 16px}.vx-el .stat b{display:block;font-size:1.4rem;font-weight:800}.vx-el .stat span{font-size:.75rem;color:var(--muted)}
.vx-el .filters{display:grid;grid-template-columns:minmax(0,2fr) repeat(2,minmax(0,1fr)) auto;gap:10px;padding:14px;align-items:center}
.vx-el .filters input,.vx-el .filters select{width:100%;border:1px solid var(--line);border-radius:10px;font-size:.82rem;background:transparent}
.vx-el .row{display:grid;grid-template-columns:130px minmax(0,1.2fr) minmax(0,1.6fr) auto;gap:14px;align-items:center;width:100%;padding:12px 16px;border-top:1px solid var(--line);text-align:left}
.vx-el .row:hover{background:var(--soft)}
.vx-el .row.on{background:color-mix(in srgb,var(--p) 7%,transparent)}
.vx-el .muted{color:var(--muted);font-size:.74rem}
.vx-el .pill{display:inline-flex;padding:2px 9px;border-radius:999px;font-size:.68rem;font-weight:800;white-space:nowrap}
.vx-el .pill.sent{background:#dcfce7;color:#15803d}.vx-el .pill.failed{background:#fee2e2;color:#b91c1c}.vx-el .pill.sending{background:#fef3c7;color:#b45309}.vx-el .pill.held{background:#f1f5f9;color:#475569}.vx-el .pill.test{background:#ede9fe;color:#6d28d9}
.vx-el .panel{position:fixed;inset:0;z-index:60;display:flex;justify-content:flex-end;background:rgba(15,23,42,.45)}
.vx-el .panel-inner{width:min(720px,100vw);height:100%;background:#fff;display:flex;flex-direction:column}
.dark .vx-el .panel-inner{background:#0f172a}
.vx-el .panel iframe{flex:1;width:100%;border:0;background:#f4f2fb}
.vx-el .btn{display:inline-flex;align-items:center;gap:6px;min-height:34px;padding:0 12px;border:1px solid var(--line);border-radius:10px;font-size:.78rem;font-weight:700}
@media(max-width:800px){.vx-el .filters{grid-template-columns:1fr 1fr}.vx-el .filters>:first-child{grid-column:1/-1}.vx-el .row{grid-template-columns:minmax(0,1fr) auto}.vx-el .row .when{grid-column:1/-1;order:-1}.vx-el .row .subj{grid-column:1/-1}}
</style>

<div class="vx-el">
    <div class="stats">
        <div class="card stat"><b>{{ number_format($stats['today']) }}</b><span>Sent in the last 24 hours (not counting tests)</span></div>
        <div class="card stat"><b @if($stats['failed']) style="color:#b91c1c" @endif>{{ number_format($stats['failed']) }}</b><span>Failed or unconfirmed (24h)</span></div>
        <div class="card stat"><b>{{ number_format($stats['total']) }}</b><span>All emails on record</span></div>
    </div>

    <section class="card">
        <div class="filters">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search recipient or subject…">
            <select wire:model.live="status"><option value="">All statuses</option>@foreach($statusLabels as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <select wire:model.live="event"><option value="">All emails</option>@foreach(\App\Support\NotificationCatalog::events() as $k => $e)<option value="{{ $k }}">{{ $e['label'] }}</option>@endforeach</select>
            <label class="flex items-center gap-2 text-xs font-semibold whitespace-nowrap"><input type="checkbox" wire:model.live="showTests" class="rounded"> Show tests</label>
        </div>

        @forelse($logs as $log)
            <button type="button" wire:key="log-{{ $log->id }}" wire:click="open({{ $log->id }})" @class(['row', 'on' => $openId === $log->id])>
                <span class="when muted">{{ $log->created_at->timezone(auth()->user()?->timezone ?: config('app.timezone'))->format('M j, g:i A') }}</span>
                <span class="min-w-0"><span class="block truncate text-sm font-semibold">{{ $log->to_name ?: $log->to_email }}</span>@if($log->to_name)<span class="block truncate muted">{{ $log->to_email }}</span>@endif</span>
                <span class="subj min-w-0"><span class="block truncate text-sm">{{ $log->subject ?: '(no subject)' }}</span><span class="block truncate muted">{{ $this->eventLabel($log->event) }}</span></span>
                <span class="flex flex-col items-end gap-1">
                    <span class="pill {{ $log->status }}">{{ $statusLabels[$log->status] ?? ucfirst($log->status) }}</span>
                    @if($log->is_test)<span class="pill test">Test</span>@endif
                </span>
            </button>
        @empty
            <div class="p-10 text-center text-sm" style="color:var(--muted)">No emails{{ $search || $status || $event ? ' match these filters' : ' have been sent yet' }}. Use “Send test” in Settings → Notifications to see one here.</div>
        @endforelse

        @if($logs->hasPages())
            <div class="flex items-center justify-between gap-3 p-3" style="border-top:1px solid var(--line)">
                <button type="button" class="btn" wire:click="previousPage" @disabled($logs->onFirstPage())>Previous</button>
                <span class="text-xs font-semibold">Page {{ $logs->currentPage() }} of {{ $logs->lastPage() }}</span>
                <button type="button" class="btn" wire:click="nextPage" @disabled(! $logs->hasMorePages())>Next</button>
            </div>
        @endif
    </section>

    @if($opened)
        <div class="panel" wire:key="email-{{ $opened->id }}" @keydown.escape.window="$wire.close()" @click.self="$wire.close()">
            <div class="panel-inner">
                <div class="p-4" style="border-bottom:1px solid var(--line)">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="muted font-bold uppercase tracking-wider">{{ $this->eventLabel($opened->event) }}</div>
                            <div class="mt-1 text-base font-bold">{{ $opened->subject ?: '(no subject)' }}</div>
                        </div>
                        <button type="button" class="btn" wire:click="close">Close</button>
                    </div>
                    <dl class="mt-3 grid gap-1 text-xs" style="grid-template-columns:auto 1fr">
                        <dt class="muted pr-3">To</dt><dd class="break-all">{{ $opened->to_name ? $opened->to_name.' <'.$opened->to_email.'>' : $opened->to_email }}</dd>
                        <dt class="muted pr-3">Queued</dt><dd>{{ $opened->created_at->format('D, M j Y · g:i:s A') }}</dd>
                        <dt class="muted pr-3">Status</dt><dd><span class="pill {{ $opened->status }}">{{ $statusLabels[$opened->status] ?? $opened->status }}</span>@if($opened->sent_at) <span class="muted">at {{ $opened->sent_at->format('g:i:s A') }}</span>@endif @if($opened->is_test)<span class="pill test">Test</span>@endif</dd>
                        @if($opened->error)<dt class="muted pr-3">Error</dt><dd style="color:#b91c1c">{{ $opened->error }}</dd>@endif
                    </dl>
                </div>
                @if($opened->html)
                    <iframe title="Email as sent" sandbox srcdoc="{{ $opened->html }}"></iframe>
                @else
                    <pre class="flex-1 overflow-auto whitespace-pre-wrap p-4 text-sm">{{ $opened->text ?: 'No body was recorded.' }}</pre>
                @endif
            </div>
        </div>
    @endif
</div>
</x-filament-panels::page>
