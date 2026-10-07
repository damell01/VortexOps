<x-filament-panels::page>
@php
    $events = $this->events();
    $emailOn = $this->appEmailIsOn();
    $grouped = collect($events)->groupBy('group', true);
@endphp
<style>
.vx-mn{--line:#e5e7eb;--muted:#64748b;--p:var(--primary-600,#7c3aed);max-width:860px;margin:0 auto;display:grid;gap:16px}
.dark .vx-mn{--line:#253247;--muted:#94a3b8}
.vx-mn .card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px}
.dark .vx-mn .card{background:#0f172a}
.vx-mn .switch{display:inline-flex;align-items:center;gap:8px;font-size:.82rem;font-weight:600;cursor:pointer}
.vx-mn .switch input{appearance:none;width:38px;height:22px;border-radius:999px;background:#cbd5e1;position:relative;transition:.15s;cursor:pointer;flex:none}
.vx-mn .switch input:before{content:"";position:absolute;top:2px;left:2px;width:18px;height:18px;border-radius:999px;background:#fff;transition:.15s;box-shadow:0 1px 2px rgba(0,0,0,.2)}
.vx-mn .switch input:checked{background:var(--p)}.vx-mn .switch input:checked:before{left:18px}
.vx-mn .switch input:disabled{opacity:.45;cursor:not-allowed}
.vx-mn .row{display:grid;grid-template-columns:minmax(0,1fr) 92px 92px;gap:12px;align-items:center;padding:12px 0;border-top:1px solid var(--line)}
.vx-mn .head{font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)}
.vx-mn .sub{font-size:.78rem;color:var(--muted)}
.vx-mn .btn{display:inline-flex;align-items:center;min-height:30px;padding:0 10px;border:1px solid var(--line);border-radius:9px;font-size:.72rem;font-weight:700}
.vx-mn .save{position:sticky;bottom:12px;display:flex;justify-content:flex-end}
.vx-mn .save button{min-height:42px;padding:0 18px;border-radius:12px;background:var(--p);color:#fff;font-weight:800;box-shadow:0 8px 20px rgba(15,23,42,.2)}
@media(max-width:640px){.vx-mn .row{grid-template-columns:minmax(0,1fr) 64px 64px}}
.vx-mn .switch input{width:38px!important;height:22px!important;min-width:38px!important;min-height:22px!important;max-height:22px!important;padding:0!important;margin:0!important;border:0!important;border-radius:999px!important;-webkit-appearance:none!important;appearance:none!important;background-image:none!important}
.vx-mn .switch input:before{width:18px!important;height:18px!important}
/* Clear on/off: off is a grey track with an ✕ knob, on is filled with a ✓ knob. */
.vx-mn .switch input{background:#e2e8f0!important;box-shadow:inset 0 0 0 1px #cbd5e1!important}
.vx-mn .switch input:before{content:"✕"!important;display:grid;place-items:center;font-size:10px;font-weight:900;line-height:18px;text-align:center;color:#94a3b8}
.vx-mn .switch input:checked{background:var(--p)!important;box-shadow:none!important}
.vx-mn .switch input:checked:before{content:"✓"!important;color:var(--p)}
.dark .vx-mn .switch input{background:#334155!important;box-shadow:inset 0 0 0 1px #475569!important}
.vx-mn .chip input + *, .vx-mn .chip{}
.vx-mn .chip:has(input:checked):before{content:"✓";font-weight:900}
</style>

<div class="vx-mn">
    <section class="card grid gap-4 sm:grid-cols-3">
        <label class="switch"><input type="checkbox" wire:model.live="enabled"> Notifications on</label>
        <label class="switch"><input type="checkbox" wire:model="inApp" @disabled(! $enabled)> In the app (bell)</label>
        <label class="switch"><input type="checkbox" wire:model="email" @disabled(! $enabled)> By email</label>
        <p class="sub sm:col-span-3">
            Turning notifications off pauses everything except items marked <b>Required</b> — work only you can do, like a report you've been asked to file.
            @unless($emailOn) Email is currently switched off for the whole app, so nothing will be emailed right now. @endunless
        </p>
    </section>

    @forelse($grouped as $group => $items)
        <section class="card">
            <div class="row" style="border-top:0;padding-top:0">
                <div class="head" style="color:var(--p)">{{ $group }}</div>
                <div class="head text-center">In-app <button type="button" class="block w-full text-[10px] underline" wire:click="setAll('in_app', true)" style="color:var(--muted)">all on</button></div>
                <div class="head text-center">Email <button type="button" class="block w-full text-[10px] underline" wire:click="setAll('email', false)" style="color:var(--muted)">all off</button></div>
            </div>
            @foreach($items as $key => $event)
                @php $locked = $event['locked'] ?? false; @endphp
                <div class="row" wire:key="pref-{{ $key }}">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold">{{ $event['label'] }} @if($locked)<span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-300">Required</span>@endif</div>
                        <div class="sub">{{ $event['description'] }}</div>
                    </div>
                    <div class="text-center">
                        @if($event['rule']['in_app'])
                            <label class="switch" title="{{ $locked ? 'Required — this always shows in the app' : '' }}"><input type="checkbox" wire:model="prefs.{{ $key }}.in_app" @if($locked) checked disabled @endif aria-label="In-app: {{ $event['label'] }}"></label>
                        @else<span class="sub">—</span>@endif
                    </div>
                    <div class="text-center">
                        @if($event['rule']['email'])
                            <label class="switch"><input type="checkbox" wire:model="prefs.{{ $key }}.email" aria-label="Email: {{ $event['label'] }}"></label>
                        @else<span class="sub" title="Admins send this one in-app only">—</span>@endif
                    </div>
                </div>
            @endforeach
        </section>
    @empty
        <section class="card text-center sub">No notifications are set up to reach you yet.</section>
    @endforelse

    <div class="save"><button type="button" wire:click="save" wire:loading.attr="disabled">Save preferences</button></div>
</div>
</x-filament-panels::page>
