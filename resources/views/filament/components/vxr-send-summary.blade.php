{{-- The "Send summary" side panel (App\Filament\Concerns\SendsShowLossSummary). $title, $digest, $amountLabel, $countLabel --}}
@if ($this->sendOpen)
    <div class="vxr-drawer-backdrop" wire:click="closeSend"></div>
    <aside class="vxr-drawer" role="dialog" aria-modal="true" aria-labelledby="vxr-send-title" x-data x-on:keydown.escape.window="$wire.closeSend()">
        <div class="vxr-drawer-head">
            <div>
                <h2 class="vxr-drawer-title" id="vxr-send-title">{{ $title }}</h2>
                <div class="vxr-subtitle" style="margin-top:4px">{{ $digest['label'] }}</div>
            </div>
            <button type="button" class="vxr-x" wire:click="closeSend" aria-label="Close"><x-filament::icon icon="heroicon-o-x-mark" /></button>
        </div>
        <div class="vxr-drawer-body">
            <div>
                <p class="vxr-section-label">Send to</p>
                <div class="vxr-control" style="width:100%">
                    <x-filament::icon icon="heroicon-o-user-group" />
                    <select wire:model.live="sendTo" style="flex:1">
                        <option value="rule">{{ $this->sendRuleSummary() }}</option>
                        <option value="me">Just me</option>
                        <option value="pick">Owner and admins I pick</option>
                    </select>
                </div>
                @if ($sendTo === 'pick')
                    <div style="margin-top:10px;max-height:190px;overflow:auto;border:1px solid var(--vxw-border);border-radius:10px;padding:4px 12px">
                        @foreach ($this->sendPeople() as $p)
                            <label class="vxr-check"><input type="checkbox" value="{{ $p->id }}" wire:model="sendUsers"> {{ $p->name }} <span class="vxr-hint" style="margin:0">{{ $p->email }}</span></label>
                        @endforeach
                    </div>
                    @error('sendUsers')<p class="vxr-hint" style="color:var(--vxw-bad)">{{ $message }}</p>@enderror
                @endif
                <p class="vxr-hint">{{ $sendTo === 'rule' ? 'The people set for this notification in Settings → Notifications.' : ($sendTo === 'me' ? 'Only you will receive it.' : 'Only the people you tick will receive it.') }}</p>
            </div>
            <div>
                <p class="vxr-section-label">Delivery method</p>
                <label class="vxr-check"><input type="checkbox" wire:model="sendEmail"> <x-filament::icon icon="heroicon-o-envelope" style="width:20px;height:20px;color:var(--vxw-muted)" /> Email</label>
                <label class="vxr-check"><input type="checkbox" wire:model="sendInApp"> <x-filament::icon icon="heroicon-o-bell" style="width:20px;height:20px;color:var(--vxw-muted)" /> In-app</label>
                @unless (\App\Services\NotificationRouter::emailIsEnabled())
                    <p class="vxr-hint" style="color:var(--vxw-warn)">Email delivery is turned off in Settings → Notifications, so only in-app will be sent.</p>
                @endunless
                @error('sendChannels')<p class="vxr-hint" style="color:var(--vxw-bad)">{{ $message }}</p>@enderror
            </div>
            <div style="border-top:1px solid var(--vxw-border);padding-top:16px">
                <p class="vxr-section-label">Summary <span style="font-weight:400;color:var(--vxw-muted)">({{ $digest['label'] }})</span></p>
                <div style="display:grid;grid-template-columns:1fr 1fr;background:var(--vxw-surface-2);border-radius:12px;padding:16px">
                    <div style="padding-right:12px;border-right:1px solid var(--vxw-border)">
                        <div class="vxr-stat-value">{{ $digest['count'] }}</div>
                        <div class="vxr-hint" style="margin-top:2px">{{ $countLabel }}</div>
                    </div>
                    <div style="padding-left:16px">
                        <div class="vxr-stat-value {{ $digest['total'] < 0 ? 'vxr-val-bad' : '' }}">{{ ($digest['total'] < 0 ? '-$' : '$').number_format(abs($digest['total'])) }}</div>
                        <div class="vxr-hint" style="margin-top:2px">{{ $amountLabel }}</div>
                    </div>
                </div>
            </div>
            @if ($sendPreview !== '')
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center"><p class="vxr-section-label" style="margin:0">Email preview</p><button type="button" class="vxr-link" wire:click="$set('sendPreview', '')" style="background:none;border:0;cursor:pointer">Hide</button></div>
                    <iframe srcdoc="{{ $sendPreview }}" title="Email preview" style="width:100%;height:420px;border:1px solid var(--vxw-border);border-radius:10px;margin-top:8px;background:#fff"></iframe>
                </div>
            @endif
        </div>
        <div class="vxr-drawer-foot" style="display:grid;gap:10px">
            <button type="button" class="vxr-btn vxr-btn--outline vxr-btn--block" wire:click="previewSend" wire:loading.attr="disabled"><x-filament::icon icon="heroicon-o-document-text" /> Preview Summary</button>
            <button type="button" class="vxr-btn vxr-btn--primary vxr-btn--block" wire:click="sendNow" wire:loading.attr="disabled"><x-filament::icon icon="heroicon-o-paper-airplane" /> Send Summary</button>
            <p class="vxr-foot-note" style="margin:4px 0 0"><x-filament::icon icon="heroicon-o-information-circle" />
                <span>Daily and weekly schedules are managed in <a class="vxr-link" href="{{ \App\Filament\Pages\NotificationSettings::getUrl(['configure' => $this->lossEvent()]) }}">Settings → Notifications</a>@if($this->sendSchedule()) (now: {{ $this->sendSchedule() }})@endif.</span></p>
        </div>
    </aside>
@endif
