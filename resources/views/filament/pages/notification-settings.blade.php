<x-filament-panels::page>
@php
    $owner = $this->isOwner();
    $server = $this->serverAllowsEmail();
    $mailer = $this->mailer();
    $live = $server && $emailEnabled;
    $levels = \App\Support\NotificationCatalog::LEVELS;
    $needle = mb_strtolower(trim($search));
@endphp
<style>
.vx-ns{--line:#e5e7eb;--soft:#f8fafc;--muted:#64748b;--p:var(--primary-600,#7c3aed);--p-soft:color-mix(in srgb,var(--p) 10%,transparent);max-width:1180px;margin:0 auto;display:grid;gap:16px;color:inherit}
.dark .vx-ns{--line:#253247;--soft:#111c2f;--muted:#94a3b8}
.vx-ns .card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px}
.dark .vx-ns .card{background:#0f172a}
.vx-ns h2{font-size:1rem;font-weight:800}
.vx-ns .sub{font-size:.8rem;color:var(--muted)}
.vx-ns .grid2{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);gap:16px}
.vx-ns .chip{display:inline-flex;align-items:center;gap:6px;padding:5px 11px;border:1px solid var(--line);border-radius:999px;font-size:.76rem;font-weight:600;color:var(--muted);background:transparent;cursor:pointer;user-select:none}
.vx-ns .chip input{position:absolute;opacity:0;pointer-events:none}
.vx-ns .chip:has(input:checked){background:var(--p);border-color:var(--p);color:#fff}
.vx-ns .chip.involved:has(input:checked){background:#0ea5e9;border-color:#0ea5e9}
.vx-ns .pill{display:inline-flex;padding:2px 8px;border-radius:999px;font-size:.66rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
.vx-ns .pill.action{background:#fee2e2;color:#b91c1c}.vx-ns .pill.alert{background:#fef3c7;color:#b45309}.vx-ns .pill.fyi{background:#e0f2fe;color:#0369a1}.vx-ns .pill.digest{background:#ede9fe;color:#6d28d9}
.vx-ns .status{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:.74rem;font-weight:700}
.vx-ns .status.on{background:#dcfce7;color:#15803d}.vx-ns .status.off{background:#f1f5f9;color:#64748b}.vx-ns .status.warn{background:#fef3c7;color:#b45309}
.vx-ns .event{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,1.5fr) auto;gap:18px;padding:16px 0;border-top:1px solid var(--line);align-items:start}
.vx-ns .event:first-of-type{border-top:0}
.vx-ns .event.off{opacity:.55}
.vx-ns .lbl{font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:6px}
.vx-ns .switch{position:relative;display:inline-flex;align-items:center;gap:8px;font-size:.8rem;font-weight:600;cursor:pointer}
.vx-ns .switch input{appearance:none;width:36px;height:20px;border-radius:999px;background:#cbd5e1;position:relative;transition:.15s;cursor:pointer;flex:none}
.vx-ns .switch input:before{content:"";position:absolute;top:2px;left:2px;width:16px;height:16px;border-radius:999px;background:#fff;transition:.15s;box-shadow:0 1px 2px rgba(0,0,0,.2)}
.vx-ns .switch input:checked{background:var(--p)}.vx-ns .switch input:checked:before{left:18px}
.vx-ns .switch input:disabled{opacity:.5;cursor:not-allowed}
.vx-ns .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:34px;padding:0 12px;border:1px solid var(--line);border-radius:10px;font-size:.78rem;font-weight:700;background:transparent}
.vx-ns .btn:hover{background:var(--soft)}.vx-ns .btn.primary{background:var(--p);border-color:var(--p);color:#fff}
.vx-ns input[type=text],.vx-ns input[type=email],.vx-ns input[type=number],.vx-ns input[type=search]{width:100%;border:1px solid var(--line);border-radius:10px;font-size:.82rem;background:transparent}
.vx-ns .people{position:relative}
.vx-ns .people-list{position:absolute;z-index:30;top:calc(100% + 4px);left:0;width:min(320px,85vw);max-height:280px;overflow:auto;background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:0 12px 30px rgba(15,23,42,.15);padding:6px}
.dark .vx-ns .people-list{background:#0f172a}
.vx-ns .people-list label{display:flex;gap:8px;align-items:center;padding:6px 8px;border-radius:8px;font-size:.8rem;cursor:pointer}
.vx-ns .people-list label:hover{background:var(--soft)}
.vx-ns .save{position:sticky;bottom:12px;z-index:20;display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 16px;border-radius:14px;background:#0f172a;color:#fff;box-shadow:0 10px 30px rgba(15,23,42,.3)}
.vx-ns .preview{position:fixed;inset:0;z-index:60;display:flex;justify-content:flex-end;background:rgba(15,23,42,.45)}
.vx-ns .preview-panel{width:min(720px,100vw);height:100%;background:#fff;display:flex;flex-direction:column}
.dark .vx-ns .preview-panel{background:#0f172a}
.vx-ns .preview iframe{flex:1;width:100%;border:0;background:#f4f2fb}
@media(max-width:900px){.vx-ns .grid2{grid-template-columns:1fr}.vx-ns .event{grid-template-columns:1fr;gap:12px}}
.vx-ns .switch input{width:38px!important;height:22px!important;min-width:38px!important;min-height:22px!important;max-height:22px!important;padding:0!important;margin:0!important;border:0!important;border-radius:999px!important;-webkit-appearance:none!important;appearance:none!important;background-image:none!important}
.vx-ns .switch input:before{width:18px!important;height:18px!important}
/* Clear on/off: off is a grey track with an ✕ knob, on is filled with a ✓ knob. */
.vx-ns .switch input{background:#e2e8f0!important;box-shadow:inset 0 0 0 1px #cbd5e1!important}
.vx-ns .switch input:before{content:"✕"!important;display:grid;place-items:center;font-size:10px;font-weight:900;line-height:18px;text-align:center;color:#94a3b8}
.vx-ns .switch input:checked{background:var(--p)!important;box-shadow:none!important}
.vx-ns .switch input:checked:before{content:"✓"!important;color:var(--p)}
.dark .vx-ns .switch input{background:#334155!important;box-shadow:inset 0 0 0 1px #475569!important}
.vx-ns .chip input + *, .vx-ns .chip{}
.vx-ns .chip:has(input:checked):before{content:"✓";font-weight:900}
</style>

<div class="vx-ns">
    {{-- ── Global email controls ───────────────────────────────── --}}
    <div class="grid2">
        <section class="card">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2>Email for the whole app</h2>
                    <p class="sub mt-1">The owner's master switch. Off means no notification emails go to anyone — in-app notifications carry on.</p>
                </div>
                <span class="status {{ $live ? 'on' : ($server ? 'off' : 'warn') }}">{{ $live ? 'Emails are on' : 'Emails are off' }}</span>
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label class="switch">
                    <input type="checkbox" wire:model.live="emailEnabled" @disabled(! $owner)>
                    Send notification emails
                </label>
                <label class="text-xs font-semibold" style="color:var(--muted)">
                    Most emails one person can get per hour
                    <input type="number" min="0" max="500" wire:model="hourlyCap" @disabled(! $owner) class="mt-1">
                    <span class="mt-1 block font-normal">Extra emails that hour are skipped (they still show in-app). 0 = no limit.</span>
                </label>
            </div>
            @unless($owner)<p class="sub mt-3">Only the owner can change these two.</p>@endunless
            @unless($server)
                <p class="mt-3 rounded-lg p-3 text-xs" style="background:#fef3c7;color:#92400e">The server's safety switch is off (<code>NOTIFICATION_EMAILS_ENABLED=false</code>), so no notification email goes out whatever is set here. Test emails still work.</p>
            @endunless
        </section>

        <section class="card">
            <h2>Test every email</h2>
            <p class="sub mt-1">Sends a sample of each email to one address — rules and preferences are ignored, and the Email Log marks them as tests.</p>
            <div class="mt-3 flex gap-2">
                <input type="email" wire:model="testAddress" placeholder="you@example.com">
                <button type="button" class="btn primary shrink-0" wire:click="sendAllTests" wire:loading.attr="disabled" wire:target="sendAllTests">Send all</button>
            </div>
            @error('testAddress')<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror
            <div class="mt-3 flex flex-wrap items-center gap-3 text-xs" style="color:var(--muted)">
                <span>Mailer: <b>{{ $mailer }}</b>@if(in_array($mailer, ['log', 'array'])) (emails are recorded, not delivered)@endif</span>
                <a href="{{ \App\Filament\Pages\EmailLog::getUrl() }}" class="font-bold" style="color:var(--p)">Open Email Log →</a>
            </div>
        </section>
    </div>

    {{-- ── Per-event rules ─────────────────────────────────────── --}}
    <section class="card">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2>What gets sent, and to whom</h2>
                <p class="sub mt-1">Tick who gets each notification. People can still turn off what reaches them in their profile — except in-app items marked “Required”.</p>
            </div>
            <div style="width:min(280px,100%)"><input type="search" wire:model.live.debounce.250ms="search" placeholder="Find a notification…"></div>
        </div>

        @foreach(\App\Support\NotificationCatalog::grouped() as $group => $events)
            @php $events = $needle === '' ? $events : array_filter($events, fn ($e) => str_contains(mb_strtolower($e['label'].' '.$e['description']), $needle)); @endphp
            @continue(empty($events))
            <div class="mt-5">
                <div class="lbl" style="font-size:.74rem;color:var(--p)">{{ $group }}</div>
                @foreach($events as $key => $event)
                    @php $rule = $rules[$key]; $reach = $this->reach($key); @endphp
                    <div wire:key="ev-{{ $key }}" @class(['event', 'off' => ! ($rule['enabled'] ?? true)])>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <b class="text-sm">{{ $event['label'] }}</b>
                                <span class="pill {{ $event['level'] }}">{{ $levels[$event['level']] }}</span>
                                @if($event['locked'] ?? false)<span class="pill" style="background:#f1f5f9;color:#475569">Required in-app</span>@endif
                            </div>
                            <p class="sub mt-1">{{ $event['description'] }}</p>
                            <p class="mt-2 text-xs font-semibold" style="color:var(--muted)">
                                Reaches {{ $reach }} {{ Str::plural('person', $reach) }}@if($rule['involved'] && isset($event['involved_label'])) + {{ strtolower($event['involved_label']) }}@endif
                            </p>
                        </div>

                        <div class="min-w-0">
                            <div class="lbl">Who gets it</div>
                            <div class="flex flex-wrap gap-1.5">
                                @if(isset($event['involved_label']))
                                    <label class="chip involved"><input type="checkbox" wire:model.live="rules.{{ $key }}.involved">{{ $event['involved_label'] }}</label>
                                @endif
                                @foreach($this->audiences as $aKey => $aLabel)
                                    <label class="chip"><input type="checkbox" value="{{ $aKey }}" wire:model.live="rules.{{ $key }}.roles">{{ $aLabel }}</label>
                                @endforeach
                            </div>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                <div class="people" x-data="{ open: false, q: '' }" @click.outside="open = false">
                                    <button type="button" class="btn w-full justify-between" @click="open = !open">
                                        <span>Specific people @if(count($rule['users']))<b>({{ count($rule['users']) }})</b>@endif</span>
                                        <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4" />
                                    </button>
                                    <div class="people-list" x-show="open" x-cloak>
                                        <input type="search" x-model="q" placeholder="Search people…" class="mb-1">
                                        @foreach($this->people as $person)
                                            <label x-show="!q || @js(mb_strtolower($person->name.' '.$person->email)).includes(q.toLowerCase())">
                                                <input type="checkbox" value="{{ $person->id }}" wire:model.live="rules.{{ $key }}.users">
                                                <span class="min-w-0"><span class="block truncate font-semibold">{{ $person->name }}</span><span class="block truncate text-xs" style="color:var(--muted)">{{ $person->email }}</span></span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                                <div>
                                    <input type="text" wire:model.blur="rules.{{ $key }}.emails" placeholder="Also email (no login needed)">
                                    @error("rules.{$key}.emails")<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2.5" style="min-width:170px">
                            <label class="switch"><input type="checkbox" wire:model.live="rules.{{ $key }}.enabled"> Send this</label>
                            <label class="switch"><input type="checkbox" wire:model.live="rules.{{ $key }}.in_app"> In-app</label>
                            <label class="switch" title="{{ $live ? '' : 'Email is off for the whole app' }}"><input type="checkbox" wire:model.live="rules.{{ $key }}.email"> Email @unless($live)<span class="text-[10px]" style="color:var(--muted)">(app email off)</span>@endunless</label>
                            <div class="flex gap-1.5 pt-1">
                                <button type="button" class="btn" wire:click="preview('{{ $key }}')">Preview</button>
                                <button type="button" class="btn" wire:click="sendTest('{{ $key }}')" wire:loading.attr="disabled" wire:target="sendTest('{{ $key }}')">Send test</button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </section>

    <div class="save">
        <span class="text-sm">Changes apply to the next notification sent.</span>
        <button type="button" class="btn primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">Save notification settings</button>
    </div>

    {{-- ── Email preview ───────────────────────────────────────── --}}
    @if($previewEvent)
        <div class="preview" wire:key="preview-{{ $previewEvent }}" @keydown.escape.window="$wire.closePreview()" @click.self="$wire.closePreview()">
            <div class="preview-panel">
                <div class="flex items-center justify-between gap-3 p-4" style="border-bottom:1px solid var(--line)">
                    <div><div class="lbl">Email preview</div><b>{{ \App\Support\NotificationCatalog::label($previewEvent) }}</b></div>
                    <div class="flex gap-2">
                        <button type="button" class="btn" wire:click="sendTest('{{ $previewEvent }}')">Send test</button>
                        <button type="button" class="btn" wire:click="closePreview">Close</button>
                    </div>
                </div>
                <iframe title="Email preview" sandbox srcdoc="{{ $previewHtml }}"></iframe>
            </div>
        </div>
    @endif
</div>
</x-filament-panels::page>
