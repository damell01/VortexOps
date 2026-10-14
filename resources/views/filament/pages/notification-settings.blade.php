<x-filament-panels::page>
@php
    $owner = $this->isOwner();
    $server = $this->serverAllowsEmail();
    $mailer = $this->mailer();
    $live = $server && $emailEnabled;
    $catalog = \App\Support\NotificationCatalog::events();
    $groups = array_keys(\App\Support\NotificationCatalog::grouped());
    $groupIcons = ['Shows' => 'heroicon-o-video-camera', 'Reports' => 'heroicon-o-document-text', 'Inventory' => 'heroicon-o-cube', 'Fulfillment' => 'heroicon-o-truck', 'Payroll' => 'heroicon-o-banknotes', 'Summaries' => 'heroicon-o-chart-bar', 'System' => 'heroicon-o-cog-6-tooth'];
    $levelTone = ['action' => 'info', 'alert' => 'bad', 'fyi' => 'accent', 'digest' => 'ok'];
    $visible = $this->visibleEvents();
    $count = fn ($g) => count(array_filter($catalog, fn ($e) => $g === 'all' || $e['group'] === $g));
@endphp

<div class="vxr" data-vx-page="notification-settings" style="max-width:1180px">
    <div class="vxr-head">
        <div>
            <h1 class="vxr-title">Notifications</h1>
            <p class="vxr-subtitle">Manage who receives alerts and how they are delivered.</p>
        </div>
        <div class="vxr-actions">
            <a class="vxr-btn" href="{{ \App\Filament\Pages\EmailLog::getUrl() }}"><x-filament::icon icon="heroicon-o-document-text" /> Delivery Log</a>
            <button type="button" class="vxr-btn" wire:click="$set('emailPanel', true)"><x-filament::icon icon="heroicon-o-paper-airplane" /> Test Email</button>
        </div>
    </div>

    @if (! $live)
        <div class="vxr-banner vxr-banner--warn" style="flex-wrap:wrap">
            <x-filament::icon icon="heroicon-s-exclamation-circle" />
            <span style="flex:1;min-width:220px"><b style="display:block;font-size:15px">Email delivery is disabled</b>
                {{ $server ? 'In-app notifications remain active.' : 'The server\'s safety switch (NOTIFICATION_EMAILS_ENABLED) is off, so no notification email can leave. In-app notifications remain active.' }}</span>
            @if ($owner && $server)
                <label style="display:flex;align-items:center;gap:10px;font-weight:600;color:var(--vxw-text);font-size:14px">Enable email delivery
                    <span class="vxr-switch"><input type="checkbox" wire:click="toggleEmail" @checked($emailEnabled)><span></span></span></label>
            @endif
            <button type="button" class="vxr-btn vxr-btn--outline" style="margin-left:0" wire:click="$set('emailPanel', true)">Manage Email</button>
        </div>
    @else
        <div class="vxr-banner" style="border-color:var(--vxw-ok-line);background:var(--vxw-ok-soft);color:var(--vxw-ok);flex-wrap:wrap">
            <x-filament::icon icon="heroicon-s-check-circle" />
            <span style="flex:1"><b>Email delivery is on.</b> Mailer: {{ $mailer }}@if(in_array($mailer, ['log', 'array'])) — emails are recorded, not delivered @endif.</span>
            <button type="button" class="vxr-btn vxr-btn--sm" wire:click="$set('emailPanel', true)">Manage Email</button>
        </div>
    @endif

    <div class="vxr-tabs" role="tablist">
        @foreach (array_merge(['all'], $groups) as $g)
            <button type="button" role="tab" wire:click="$set('group', '{{ $g }}')" @class(['vxr-tab', 'is-active' => $group === $g])>{{ $g === 'all' ? 'All' : $g }} ({{ $count($g) }})</button>
        @endforeach
    </div>

    <div class="vxr-toolbar">
        <label class="vxr-control vxr-search"><x-filament::icon icon="heroicon-o-magnifying-glass" /><input type="search" wire:model.live.debounce.250ms="search" placeholder="Search notifications…"></label>
        <label class="vxr-check" style="padding:0 6px"><input type="checkbox" wire:model.live="enabledOnly"> Enabled only</label>
    </div>

    <div style="display:grid;gap:12px">
        @forelse ($visible as $key => $event)
            @php $rule = $rules[$key]; $on = (bool) ($rule['enabled'] ?? true); @endphp
            <div wire:key="ev-{{ $key }}" class="vxr-card" style="display:flex;gap:16px;align-items:center;padding:18px 20px;flex-wrap:wrap;{{ $configure === $key ? 'border-color:var(--vxw-accent);box-shadow:var(--vxw-ring);' : '' }}{{ $on ? '' : 'opacity:.75;' }}">
                <span class="vxr-stat-icon vxr-tone-{{ $levelTone[$event['level']] ?? 'accent' }}" style="border-radius:14px"><x-filament::icon :icon="$groupIcons[$event['group']] ?? 'heroicon-o-bell'" /></span>
                <div style="flex:1;min-width:220px">
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><b style="font-size:16px">{{ $event['label'] }}</b>
                        @if ($event['locked'] ?? false)<span class="vxr-pill">Required in-app</span>@endif
                        @if ($key === 'show_lost_money')<span class="vxr-pill vxr-pill--warn">After payroll</span>@endif
                    </div>
                    <div class="vxr-hint" style="margin-top:3px;font-size:13.5px">{{ $event['description'] }}</div>
                    <div class="vxr-hint" style="margin-top:4px">{{ $on ? $this->summaryLine($key) : 'Not enabled' }}</div>
                </div>
                <span class="vxr-switch" title="{{ $on ? 'On' : 'Off' }}"><input type="checkbox" aria-label="Send {{ $event['label'] }}" wire:click="toggleEnabled('{{ $key }}')" @checked($on)><span></span></span>
                <button type="button" class="vxr-btn" wire:click="openConfigure('{{ $key }}')">Configure</button>
            </div>
        @empty
            <div class="vxr-card"><div class="vxr-empty"><b>No notifications match</b>Try another tab or clear the search.</div></div>
        @endforelse
    </div>
</div>

{{-- ── Configure panel ─────────────────────────────────────────────── --}}
@if ($configure && isset($catalog[$configure]))
    @php
        $key = $configure; $event = $catalog[$key]; $rule = $rules[$key];
        $schedule = $rule['schedule'] ?? null;
        $dirty = $this->isDirty($key);
        $people = $this->people->keyBy('id');
        $times = []; for ($m = 0; $m < 1440; $m += 30) { $times[sprintf('%02d:%02d', intdiv($m, 60), $m % 60)] = \Illuminate\Support\Carbon::createFromTime(intdiv($m, 60), $m % 60)->format('g:i A'); }
    @endphp
    <div class="vxr-drawer-backdrop" wire:click="closeConfigure"></div>
    <aside class="vxr-drawer" style="width:min(500px,100vw)" role="dialog" aria-modal="true" x-data x-on:keydown.escape.window="$wire.closeConfigure()">
        <div class="vxr-drawer-head">
            <div><h2 class="vxr-drawer-title">{{ $event['label'] }}</h2><p class="vxr-hint" style="font-size:13.5px">{{ $event['description'] }}</p></div>
            <button type="button" class="vxr-x" wire:click="closeConfigure" aria-label="Close"><x-filament::icon icon="heroicon-o-x-mark" /></button>
        </div>
        <div class="vxr-drawer-body">
            <div style="display:flex;justify-content:space-between;align-items:center;padding-bottom:16px;border-bottom:1px solid var(--vxw-border)">
                <p class="vxr-section-label" style="margin:0">Enabled</p>
                <span class="vxr-switch"><input type="checkbox" wire:model.live="rules.{{ $key }}.enabled"><span></span></span>
            </div>

            <div style="padding-bottom:16px;border-bottom:1px solid var(--vxw-border)">
                <p class="vxr-section-label">Recipients</p>
                <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;padding:8px;border:1px solid var(--vxw-border);border-radius:10px;min-height:46px">
                    @foreach ($rule['roles'] as $aKey)
                        <span class="vxr-pill" style="padding:5px 8px 5px 12px;font-size:13.5px">{{ $this->audiences[$aKey] ?? $aKey }} <button type="button" wire:click="removeAudience('{{ $key }}', '{{ $aKey }}')" aria-label="Remove {{ $this->audiences[$aKey] ?? $aKey }}" style="background:none;border:0;cursor:pointer;color:inherit;display:inline-flex"><x-filament::icon icon="heroicon-m-x-mark" /></button></span>
                    @endforeach
                    @foreach ($rule['users'] as $uid)
                        @if ($p = $people->get((int) $uid))
                            <span class="vxr-pill vxr-pill--accent" style="padding:5px 8px 5px 12px;font-size:13.5px">{{ $p->name }} <button type="button" wire:click="removeUser('{{ $key }}', '{{ $uid }}')" aria-label="Remove {{ $p->name }}" style="background:none;border:0;cursor:pointer;color:inherit;display:inline-flex"><x-filament::icon icon="heroicon-m-x-mark" /></button></span>
                        @endif
                    @endforeach
                    <select class="vxr-select" style="border:0;flex:1;min-width:150px;height:32px" x-on:change="$wire.addRecipient('{{ $key }}', $event.target.value); $event.target.value = ''" aria-label="Add recipient">
                        <option value="">Add role or person…</option>
                        <optgroup label="Roles">
                            @foreach (array_diff_key($this->audiences, array_flip($rule['roles'])) as $aKey => $aLabel)
                                <option value="{{ $aKey }}">{{ $aLabel }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="People">
                            @foreach ($this->people->reject(fn ($p) => in_array((string) $p->id, array_map('strval', $rule['users']), true)) as $p)
                                <option value="u:{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </optgroup>
                    </select>
                </div>
                <p class="vxr-hint">Choose roles or specific people · reaches {{ $this->reach($key) }} {{ \Illuminate\Support\Str::plural('person', $this->reach($key)) }}.</p>
                @if (isset($event['involved_label']))
                    <label class="vxr-check"><input type="checkbox" wire:model.live="rules.{{ $key }}.involved"> Also {{ strtolower($event['involved_label']) }}</label>
                @endif
                <label class="vxr-control" style="width:100%;margin-top:6px"><x-filament::icon icon="heroicon-o-at-symbol" /><input type="text" wire:model.blur="rules.{{ $key }}.emails" placeholder="Also email (no login needed)" style="flex:1"></label>
                @error("rules.{$key}.emails")<p class="vxr-hint" style="color:var(--vxw-bad)">{{ $message }}</p>@enderror
            </div>

            <div style="padding-bottom:16px;border-bottom:1px solid var(--vxw-border)">
                <p class="vxr-section-label">Delivery</p>
                <div style="display:flex;gap:28px">
                    <label class="vxr-check"><input type="checkbox" wire:model.live="rules.{{ $key }}.in_app"> In-app</label>
                    <label class="vxr-check"><input type="checkbox" wire:model.live="rules.{{ $key }}.email"> Email</label>
                </div>
                @if (($rule['email'] ?? false) && ! $live)
                    <div class="vxr-banner vxr-banner--warn" style="padding:9px 12px;font-size:13.5px;margin-top:6px"><x-filament::icon icon="heroicon-s-exclamation-triangle" /> Email will send once delivery is enabled.</div>
                @endif
            </div>

            @if ($schedule)
                <div style="padding-bottom:16px;border-bottom:1px solid var(--vxw-border)">
                    <p class="vxr-section-label">Schedule</p>
                    <div class="vxr-seg" style="width:100%">
                        @foreach (\App\Support\NotificationSchedule::MODES as $m => $mLabel)
                            <button type="button" style="flex:1;justify-content:center" wire:click="$set('rules.{{ $key }}.schedule.mode', '{{ $m }}')" @class(['is-active' => $schedule['mode'] === $m])>{{ $mLabel }}</button>
                        @endforeach
                    </div>
                    @if ($schedule['mode'] !== 'immediate')
                        <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
                            @if ($schedule['mode'] === 'weekly')
                                <select class="vxr-select" style="flex:1;min-width:120px" wire:model.live="rules.{{ $key }}.schedule.day" aria-label="Day">@foreach (\App\Support\NotificationSchedule::DAYS as $d => $dLabel)<option value="{{ $d }}">{{ $dLabel }}</option>@endforeach</select>
                            @endif
                            <select class="vxr-select" style="flex:1;min-width:110px" wire:model.live="rules.{{ $key }}.schedule.time" aria-label="Time">@foreach ($times as $v => $tLabel)<option value="{{ $v }}">{{ $tLabel }}</option>@endforeach</select>
                            <select class="vxr-select" style="flex:1.3;min-width:150px" wire:model.live="rules.{{ $key }}.schedule.timezone" aria-label="Time zone">
                                @foreach (['America/Chicago', 'America/New_York', 'America/Denver', 'America/Phoenix', 'America/Los_Angeles', 'UTC'] as $zone)<option value="{{ $zone }}">{{ $zone }}</option>@endforeach
                            </select>
                        </div>
                        <p class="vxr-hint">{{ $schedule['mode'] === 'daily' ? 'Each summary covers the day before.' : 'Each summary covers the last full Monday–Sunday week.' }}</p>
                    @else
                        <p class="vxr-hint">One notification per show, as soon as it qualifies.</p>
                    @endif
                </div>

                <div style="padding-bottom:16px;border-bottom:1px solid var(--vxw-border)">
                    <p class="vxr-section-label">Include businesses</p>
                    <select class="vxr-select" style="width:100%" wire:model.live="rules.{{ $key }}.schedule.business" aria-label="Businesses">
                        <option value="">All businesses</option>
                        @foreach (\App\Models\Business::ordered() as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                    </select>
                </div>

                @if ($schedule['mode'] !== 'immediate')
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                        <div><p class="vxr-section-label" style="margin:0">Skip empty summaries</p><p class="vxr-hint" style="margin-top:2px">Don't send if there is nothing to report.</p></div>
                        <span class="vxr-switch"><input type="checkbox" wire:model.live="rules.{{ $key }}.schedule.skip_empty"><span></span></span>
                    </div>
                @endif
            @endif

            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <button type="button" class="vxr-btn vxr-btn--outline" wire:click="preview('{{ $key }}')"><x-filament::icon icon="heroicon-o-eye" /> Preview</button>
                <button type="button" class="vxr-btn vxr-btn--outline" wire:click="sendTest('{{ $key }}')" wire:loading.attr="disabled"><x-filament::icon icon="heroicon-o-paper-airplane" /> Send Test</button>
            </div>
            @error('testAddress')<p class="vxr-hint" style="color:var(--vxw-bad)">{{ $message }} (Test Email at the top.)</p>@enderror
        </div>
        <div class="vxr-drawer-foot">
            @if ($dirty)<span class="vxr-hint" style="margin:0 auto 0 0;display:flex;align-items:center;gap:6px"><span style="width:9px;height:9px;border-radius:9px;background:#f59e0b"></span> Changes not saved</span>@endif
            <button type="button" class="vxr-btn" wire:click="closeConfigure">Cancel</button>
            <button type="button" class="vxr-btn vxr-btn--primary" wire:click="saveConfigure" wire:loading.attr="disabled">Save Changes</button>
        </div>
    </aside>
@endif

{{-- ── Email settings / test panel ─────────────────────────────────── --}}
@if ($emailPanel)
    <div class="vxr-drawer-backdrop" wire:click="$set('emailPanel', false)"></div>
    <aside class="vxr-drawer" role="dialog" aria-modal="true" x-data x-on:keydown.escape.window="$wire.set('emailPanel', false)">
        <div class="vxr-drawer-head">
            <div><h2 class="vxr-drawer-title">Email</h2><p class="vxr-hint" style="font-size:13.5px">Mailer: <b>{{ $mailer }}</b>@if(in_array($mailer, ['log', 'array'])) — emails are recorded, not delivered @endif</p></div>
            <button type="button" class="vxr-x" wire:click="$set('emailPanel', false)" aria-label="Close"><x-filament::icon icon="heroicon-o-x-mark" /></button>
        </div>
        <div class="vxr-drawer-body">
            <div style="padding-bottom:16px;border-bottom:1px solid var(--vxw-border)">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                    <div><p class="vxr-section-label" style="margin:0">Send notification emails</p><p class="vxr-hint" style="margin-top:2px">The owner's master switch. Off means no notification emails — in-app carries on.</p></div>
                    <span class="vxr-switch"><input type="checkbox" wire:model.live="emailEnabled" @disabled(! $owner)><span></span></span>
                </div>
                <label style="display:block;margin-top:14px"><span class="vxr-section-label" style="display:block">Most emails one person can get per hour</span>
                    <input type="number" min="0" max="500" wire:model="hourlyCap" @disabled(! $owner) class="vxr-select" style="width:140px;padding-right:12px;background-image:none">
                    <span class="vxr-hint" style="display:block">Extra emails that hour are skipped (they still show in-app). 0 = no limit.</span></label>
                @error('hourlyCap')<p class="vxr-hint" style="color:var(--vxw-bad)">{{ $message }}</p>@enderror
                @unless ($owner)<p class="vxr-hint">Only the owner can change these.</p>@endunless
                @unless ($server)<div class="vxr-banner vxr-banner--warn" style="margin-top:10px;font-size:13px">The server's safety switch is off (NOTIFICATION_EMAILS_ENABLED=false), so no notification email can leave until it is turned on in the server settings.</div>@endunless
                @if ($owner)<button type="button" class="vxr-btn vxr-btn--primary" style="margin-top:12px" wire:click="saveEmailSettings">Save email settings</button>@endif
            </div>
            <div>
                <p class="vxr-section-label">Test every email</p>
                <p class="vxr-hint" style="margin-top:0">Sends a sample of each email to one address — rules and preferences are ignored, and the Delivery Log marks them as tests.</p>
                <div style="display:flex;gap:8px;margin-top:8px">
                    <label class="vxr-control" style="flex:1"><x-filament::icon icon="heroicon-o-envelope" /><input type="email" wire:model="testAddress" placeholder="you@example.com" style="flex:1"></label>
                    <button type="button" class="vxr-btn vxr-btn--primary" wire:click="sendAllTests" wire:loading.attr="disabled" wire:target="sendAllTests">Send all</button>
                </div>
                @error('testAddress')<p class="vxr-hint" style="color:var(--vxw-bad)">{{ $message }}</p>@enderror
            </div>
        </div>
    </aside>
@endif

{{-- ── Email preview ───────────────────────────────────────────────── --}}
@if ($previewEvent)
    <div class="vxr-drawer-backdrop" style="z-index:1005" wire:click="closePreview"></div>
    <aside class="vxr-drawer" style="width:min(720px,100vw);z-index:1010" role="dialog" aria-modal="true" wire:key="preview-{{ $previewEvent }}" x-data x-on:keydown.escape.window="$wire.closePreview()">
        <div class="vxr-drawer-head">
            <div><p class="vxr-hint" style="margin:0">Email preview</p><h2 class="vxr-drawer-title">{{ \App\Support\NotificationCatalog::label($previewEvent) }}</h2></div>
            <div style="display:flex;gap:8px"><button type="button" class="vxr-btn vxr-btn--sm" wire:click="sendTest('{{ $previewEvent }}')">Send test</button><button type="button" class="vxr-x" wire:click="closePreview" aria-label="Close"><x-filament::icon icon="heroicon-o-x-mark" /></button></div>
        </div>
        <iframe title="Email preview" sandbox srcdoc="{{ $previewHtml }}" style="flex:1;width:100%;border:0;background:#f4f2fb"></iframe>
    </aside>
@endif
</x-filament-panels::page>
