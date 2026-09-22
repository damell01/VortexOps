@php
    $groups = $this->groups();
    $summary = $this->quickSummary();
    $allShows = collect($groups['needs_you'])->map(fn ($s) => $s + ['bucket' => 'needs_you'])
        ->concat(collect($groups['upcoming'])->map(fn ($s) => $s + ['bucket' => 'upcoming']))
        ->concat(collect($groups['waiting'])->map(fn ($s) => $s + ['bucket' => 'waiting']))
        ->concat(collect($groups['done'])->map(fn ($s) => $s + ['bucket' => 'done']))
        ->values();

    $tones = [
        'danger' => 'vx-status-danger',
        'warning' => 'vx-status-warning',
        'info' => 'vx-status-info',
        'success' => 'vx-status-success',
        'gray' => 'vx-status-gray',
    ];
@endphp

<x-filament-panels::page>
    <style>
        .fi-sidebar { display:none!important; }
        .fi-topbar-open-sidebar-btn,.fi-topbar-close-sidebar-btn { display:none!important; }
        .fi-main-ctn { margin-inline-start:0!important; }
        .fi-main { width:100%!important;max-width:none!important; }
        .vx-show-shell{--vx-border:#e2e8f0;--vx-muted:#64748b;--vx-ink:#0f172a}
        .dark .vx-show-shell{--vx-border:#334155;--vx-muted:#94a3b8;--vx-ink:#f8fafc}
        .vx-summary{border:1px solid var(--vx-border);border-radius:16px;background:#fff;padding:18px;min-height:108px}
        .dark .vx-summary{background:#111827}
        .vx-summary-warning{background:linear-gradient(135deg,#fffbeb,#fff);border-color:#fde68a}
        .dark .vx-summary-warning{background:#1c1917;border-color:#78350f}
        .vx-summary-info{background:linear-gradient(135deg,#eff6ff,#fff);border-color:#bfdbfe}
        .dark .vx-summary-info{background:#111827;border-color:#1e3a8a}
        .vx-summary-success{background:linear-gradient(135deg,#ecfdf5,#fff);border-color:#a7f3d0}
        .dark .vx-summary-success{background:#111827;border-color:#064e3b}
        .vx-filter{border:1px solid var(--vx-border);border-radius:10px;padding:9px 14px;font-size:13px;font-weight:700;color:var(--vx-muted);background:#fff;transition:.15s}
        .dark .vx-filter{background:#111827}
        .vx-filter:hover{border-color:#a5b4fc;color:#4f46e5}
        .vx-filter-active{color:#fff!important;background:linear-gradient(135deg,#6366f1,#7c3aed)!important;border-color:transparent!important;box-shadow:0 5px 15px rgba(99,102,241,.22)}
        .vx-show-card{display:flex;flex-direction:column;min-height:205px;border:1px solid var(--vx-border);border-radius:15px;background:#fff;padding:16px;transition:.18s ease}
        .dark .vx-show-card{background:#111827}
        .vx-show-card:hover{transform:translateY(-1px);box-shadow:0 10px 28px rgba(15,23,42,.08)}
        .vx-show-card-needs_you{border-color:#fcd34d}
        .vx-status{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:10px;font-weight:800;line-height:1}
        .vx-status-warning{background:#fff7ed;color:#c2410c}.vx-status-danger{background:#fef2f2;color:#b91c1c}
        .vx-status-info{background:#eff6ff;color:#1d4ed8}.vx-status-success{background:#ecfdf5;color:#047857}.vx-status-gray{background:#f1f5f9;color:#475569}
        .dark .vx-status-warning{background:#431407;color:#fdba74}.dark .vx-status-danger{background:#450a0a;color:#fca5a5}
        .dark .vx-status-info{background:#172554;color:#93c5fd}.dark .vx-status-success{background:#022c22;color:#6ee7b7}.dark .vx-status-gray{background:#1e293b;color:#cbd5e1}
        .vx-card-action{display:flex;min-height:38px;align-items:center;justify-content:center;gap:8px;border-radius:9px;background:linear-gradient(135deg,#6366f1,#6d5dfc);padding:0 14px;color:#fff;font-size:12px;font-weight:800}
        .vx-card-action:hover{filter:brightness(1.05)}
        @media(max-width:640px){.vx-summary{min-height:94px;padding:14px}.fi-page-header{margin-bottom:.75rem!important}}
    </style>

    <div class="vx-show-shell space-y-5" data-vx-page="streamer-shows" x-data="{ tab: 'needs_you', sort: 'newest' }">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="text-xs font-extrabold uppercase tracking-[.12em] text-primary-600 dark:text-primary-400">Streamer Hub</div>
                <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-gray-950 dark:text-white">My Shows</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage your shows, submit reports, and keep your inventory up to date.</p>
            </div>
            @if (filled($groups['needs_you']))
                <a href="#show-grid" @click="tab='needs_you'" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary-600 px-5 text-sm font-bold text-white shadow-sm hover:bg-primary-500">
                    <x-heroicon-m-plus class="h-4 w-4"/> New Report
                </a>
            @endif
        </section>

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <button type="button" @click="tab='needs_you'" class="vx-summary vx-summary-warning text-left">
                <div class="flex items-center gap-3"><div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300"><x-heroicon-o-document-text class="h-5 w-5"/></div><div><div class="text-2xl font-extrabold text-amber-800 dark:text-amber-300">{{ $summary['needs_you'] }}</div><div class="text-xs font-bold text-amber-700 dark:text-amber-400">Need a report</div></div></div>
                <p class="mt-2 hidden text-[11px] text-gray-500 sm:block dark:text-gray-400">Shows waiting on your report</p>
            </button>
            <button type="button" @click="tab='upcoming'" class="vx-summary text-left">
                <div class="flex items-center gap-3"><div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"><x-heroicon-o-calendar-days class="h-5 w-5"/></div><div><div class="text-2xl font-extrabold text-gray-950 dark:text-white">{{ $summary['upcoming'] }}</div><div class="text-xs font-bold text-gray-700 dark:text-gray-300">Upcoming</div></div></div>
                <p class="mt-2 hidden text-[11px] text-gray-500 sm:block dark:text-gray-400">Shows scheduled</p>
            </button>
            <button type="button" @click="tab='waiting'" class="vx-summary vx-summary-info text-left">
                <div class="flex items-center gap-3"><div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300"><x-heroicon-o-paper-airplane class="h-5 w-5"/></div><div><div class="text-2xl font-extrabold text-blue-800 dark:text-blue-300">{{ $summary['submitted'] }}</div><div class="text-xs font-bold text-blue-700 dark:text-blue-400">Submitted</div></div></div>
                <p class="mt-2 hidden text-[11px] text-gray-500 sm:block dark:text-gray-400">Reports submitted</p>
            </button>
            <button type="button" @click="tab='done'" class="vx-summary vx-summary-success text-left">
                <div class="flex items-center gap-3"><div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"><x-heroicon-o-check-circle class="h-5 w-5"/></div><div><div class="text-2xl font-extrabold text-emerald-800 dark:text-emerald-300">{{ $summary['approved'] }}</div><div class="text-xs font-bold text-emerald-700 dark:text-emerald-400">Approved</div></div></div>
                <p class="mt-2 hidden text-[11px] text-gray-500 sm:block dark:text-gray-400">Reports approved</p>
            </button>
        </section>

        <section class="flex flex-col gap-3 border-b border-gray-200 pb-4 dark:border-gray-700 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap gap-2">
                @foreach ([['needs_you','Needs Report',$summary['needs_you']],['upcoming','Upcoming',$summary['upcoming']],['waiting','Submitted',$summary['submitted']],['done','Approved',$summary['approved']],['all','All Shows',$allShows->count()]] as [$key,$label,$count])
                    <button type="button" @click="tab='{{ $key }}'" :class="tab==='{{ $key }}' && 'vx-filter-active'" class="vx-filter">{{ $label }} <span class="ml-1 opacity-70">{{ $count }}</span></button>
                @endforeach
            </div>
            <div class="flex gap-2">
                <div class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 text-xs font-semibold text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"><x-heroicon-m-calendar-days class="h-4 w-4"/> All Dates</div>
                <select x-model="sort" class="min-h-10 rounded-xl border-gray-200 bg-white py-0 pl-3 pr-8 text-xs font-semibold text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"><option value="newest">Newest First</option><option value="oldest">Oldest First</option></select>
            </div>
        </section>

        <section id="show-grid" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($allShows as $show)
                <article x-show="tab==='all' || tab==='{{ $show['bucket'] }}'" x-cloak class="vx-show-card vx-show-card-{{ $show['bucket'] }}">
                    <div class="flex items-start justify-between gap-3">
                        <span class="vx-status {{ $tones[$show['tone']] }}">{{ $show['state'] }}</span>
                        <span class="whitespace-nowrap text-[11px] font-medium text-gray-500 dark:text-gray-400">{{ $show['date'] }}</span>
                    </div>
                    <div class="mt-4 min-w-0">
                        <h3 class="line-clamp-2 text-[15px] font-extrabold leading-5 text-gray-950 dark:text-white">{{ $show['title'] }}</h3>
                        @if ($show['channel'])<p class="mt-1 text-xs font-medium text-gray-500 dark:text-gray-400">{{ $show['channel'] }}</p>@endif
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-3 text-[11px] text-gray-500 dark:text-gray-400">
                        <span class="inline-flex items-center gap-1.5"><x-heroicon-o-cube class="h-4 w-4"/> {{ number_format($show['shipments']) }} {{ Str::plural('shipment',$show['shipments']) }}</span>
                        @if ($show['state']==='Draft saved')<span class="inline-flex items-center gap-1.5 font-semibold text-amber-700 dark:text-amber-300"><x-heroicon-o-tag class="h-4 w-4"/> Draft saved</span>@endif
                        @if ($show['slow_pack'])<span class="rounded-full bg-amber-100 px-2 py-1 font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">Slow to pack</span>@endif
                    </div>
                    <div class="mt-auto pt-4">
                        @if ($show['url'])
                            <a href="{{ $show['url'] }}" class="vx-card-action">{{ $show['action'] }} <x-heroicon-m-arrow-right class="h-4 w-4"/></a>
                        @else
                            <div class="flex min-h-10 items-center justify-center rounded-lg bg-gray-100 text-xs font-bold text-gray-500 dark:bg-gray-800 dark:text-gray-400">{{ $show['state'] }}</div>
                        @endif
                        @if ($show['can_request_revision'] && $revisionFor !== $show['id'])
                            <button type="button" wire:click="askForChanges({{ $show['id'] }})" class="mt-2 w-full text-center text-[11px] font-semibold text-gray-500 hover:text-primary-600 dark:text-gray-400">Need to change something?</button>
                        @endif
                        @if ($revisionFor === $show['id'])
                            <div class="mt-3 rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800">
                                <label class="text-[11px] font-bold text-gray-700 dark:text-gray-200">What needs changing?</label>
                                <textarea wire:model="revisionReason" rows="2" class="mt-1.5 w-full rounded-lg border-gray-300 text-xs dark:border-gray-600 dark:bg-gray-900" placeholder="e.g. I logged the wrong item"></textarea>
                                <div class="mt-2 flex gap-2"><button type="button" wire:click="submitRevisionRequest" class="rounded-lg bg-primary-600 px-3 py-1.5 text-[11px] font-bold text-white">Request reopen</button><button type="button" wire:click="cancelRevisionRequest" class="text-[11px] text-gray-500">Cancel</button></div>
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </section>

        @if ($allShows->isEmpty())
            <section class="rounded-2xl border border-gray-200 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-900"><x-heroicon-o-video-camera class="mx-auto h-10 w-10 text-gray-300"/><h2 class="mt-3 font-bold text-gray-950 dark:text-white">No shows yet</h2><p class="mt-1 text-sm text-gray-500">Once you are assigned to a show it will appear here.</p></section>
        @endif

        <section class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-slate-50 p-4 dark:border-gray-700 dark:bg-gray-900"><div class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-950"><x-heroicon-o-light-bulb class="h-5 w-5"/></div><div><div class="text-xs font-bold text-gray-800 dark:text-gray-200">Need help?</div><div class="text-[11px] text-gray-500 dark:text-gray-400">Your streamer guide and support are available if you have questions.</div></div></section>
    </div>
</x-filament-panels::page>
