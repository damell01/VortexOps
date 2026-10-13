<div wire:poll.30s="refresh" class="vx-global-clock" data-vx="global-clock-banner">
    @if($entry)
        <style>
            .vx-global-clock:has(.vx-clock-banner){position:sticky;top:var(--vx-topbar-height,76px);z-index:25;margin-bottom:16px}
            .vx-clock-banner{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 18px;border:1px solid #a7f3d0;border-radius:16px;background:#ecfdf5;color:#064e3b;box-shadow:0 4px 18px rgb(6 78 59 / .06)}
            .dark .vx-clock-banner{background:#063b30;border-color:#166b52;color:#d1fae5}
            .vx-clock-banner-info{display:flex;align-items:center;gap:12px;min-width:0}
            .vx-clock-banner-info svg{width:22px;height:22px;flex-shrink:0}
            .vx-clock-banner-copy{min-width:0}.vx-clock-banner-copy strong{font-size:14px}.vx-clock-banner-copy p{font-size:12px;opacity:.8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:55vw}
            .vx-clock-banner-actions{display:flex;align-items:center;gap:14px;flex-shrink:0}
            .vx-clock-banner-time{font-size:18px;font-weight:750;font-variant-numeric:tabular-nums}
            .vx-clock-banner button{border:0;background:#047857;color:white;padding:9px 16px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;min-height:40px}
            .vx-clock-banner button:hover{background:#065f46}.vx-clock-banner button:focus-visible{outline:3px solid #34d399;outline-offset:3px}.vx-clock-banner button:disabled{opacity:.6;cursor:wait}
            @media(max-width:640px){.vx-global-clock:has(.vx-clock-banner){top:64px}.vx-clock-banner{padding:12px;gap:10px;flex-wrap:wrap}.vx-clock-banner-copy p{max-width:70vw}.vx-clock-banner-actions{width:100%;justify-content:space-between}.vx-clock-banner-actions button{padding:8px 18px}}
        </style>
        <aside class="vx-clock-banner" aria-label="Active time clock reminder" wire:key="clock-banner-{{ $entry->id }}"
            x-data="{seconds:{{ max(0, (int) $entry->clocked_in_at->diffInSeconds(now())) }}, timer:null, init(){this.timer=setInterval(()=>this.seconds++,1000)}, destroy(){clearInterval(this.timer)}, get elapsed(){return Math.floor(this.seconds/3600)+':'+String(Math.floor(this.seconds/60)%60).padStart(2,'0')+':'+String(this.seconds%60).padStart(2,'0')}}">
            <div class="vx-clock-banner-info">
                <x-filament::icon icon="heroicon-o-clock" />
                <div class="vx-clock-banner-copy"><strong>You are clocked in</strong>
                    <p>Since {{ $entry->clocked_in_at->copy()->timezone(auth()->user()?->timezone ?: \App\Support\BusinessTime::zone())->format('g:i A') }} · {{ $entry->show?->title ?: (\App\Models\TimeEntry::REASONS[$entry->reason] ?? 'Show or reason can be added later') }}</p>
                </div>
            </div>
            <div class="vx-clock-banner-actions">
                <span class="vx-clock-banner-time" x-text="elapsed" aria-label="Time since clock-in">{{ \App\Models\TimeEntry::formatMinutes((int) $entry->clocked_in_at->diffInMinutes(now())) }}</span>
                <button type="button" wire:click="clockOut" wire:loading.attr="disabled" wire:target="clockOut"><span wire:loading.remove wire:target="clockOut">Clock out</span><span wire:loading wire:target="clockOut">Clocking out…</span></button>
            </div>
        </aside>
    @endif
</div>
