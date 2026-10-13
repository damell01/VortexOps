<section class="vxw-card" data-vx="clock-widget">
    <div class="vxw-card-body">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <span @class(['flex h-11 w-11 shrink-0 items-center justify-center rounded-full', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => $entry, 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' => ! $entry])>
                    <x-filament::icon icon="heroicon-o-clock" class="h-6 w-6" />
                </span>
                <div class="min-w-0">
                    <div class="vxw-eyebrow">Time clock</div>
                    @if ($entry)
                        <div class="vxw-h2" x-data="{ s: {{ (int) $entry->clocked_in_at->diffInSeconds(now()) }} }" x-init="setInterval(() => s++, 1000)">
                            Clocked in · <span class="tabular-nums" x-text="Math.floor(s/3600) + 'h ' + String(Math.floor(s%3600/60)).padStart(2,'0') + 'm'">{{ \App\Models\TimeEntry::formatMinutes((int) $entry->clocked_in_at->diffInMinutes(now())) }}</span>
                        </div>
                        <div class="vxw-meta">Since {{ $entry->clocked_in_at->copy()->setTimezone(auth()->user()->timezone ?: \App\Support\BusinessTime::zone())->format('g:i A') }} · {{ $entry->show_id ? 'Show: '.($entry->show?->title ?? 'Show #'.$entry->show_id) : ($entry->reason ? \App\Models\TimeEntry::REASONS[$entry->reason].($entry->notes ? ' — '.$entry->notes : '') : 'No show or reason yet') }}</div>
                    @else
                        <div class="vxw-h2">Not clocked in</div>
                        <div class="vxw-meta">Today: {{ $today > 0 ? \App\Models\TimeEntry::formatMinutes($today) : 'no time yet' }}</div>
                    @endif
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($entry)
                    @unless ($changing)
                        <button type="button" wire:click="startChange" class="vxw-btn vxw-btn--ghost">{{ $entry->show_id || $entry->reason ? 'Change show / reason' : 'Link a show or reason' }}</button>
                    @endunless
                    <button type="button" wire:click="clockOut" wire:loading.attr="disabled" class="vxw-btn vxw-btn--danger" style="background:#dc2626;color:#fff;border-color:#dc2626">
                        <x-filament::icon icon="heroicon-m-stop-circle" /> Clock out
                    </button>
                @else
                    <button type="button" wire:click="clockIn" wire:loading.attr="disabled" class="vxw-btn vxw-btn--primary" style="background:#059669;border-color:#059669">
                        <x-filament::icon icon="heroicon-m-play-circle" /> Clock in
                    </button>
                @endif
            </div>
        </div>

        @if (! $entry || $changing)
            <div class="mt-4 grid gap-3" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
                <label class="vxw-field">
                    <span class="vxw-label">Link a show <span class="vxw-meta font-normal">(optional)</span></span>
                    <select wire:model.live="showId" class="vxw-select">
                        <option value="">No show — pick a reason instead</option>
                        @foreach ($shows as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
                @if ($showId === '')
                    <label class="vxw-field">
                        <span class="vxw-label">Reason</span>
                        <select wire:model.live="reason" class="vxw-select">
                            <option value="">Choose later</option>
                            @foreach ($reasons as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                        </select>
                    </label>
                    <label class="vxw-field">
                        <span class="vxw-label">Note <span class="vxw-meta font-normal">(optional)</span></span>
                        <input type="text" wire:model="note" maxlength="255" class="vxw-input" placeholder="{{ $reason === 'other' ? 'What are you working on?' : 'Anything to add' }}">
                    </label>
                @endif
            </div>
            @if ($changing)
                <div class="mt-3 flex gap-2">
                    <button type="button" wire:click="saveLink" class="vxw-btn vxw-btn--primary vxw-btn--sm">Save</button>
                    <button type="button" wire:click="$set('changing', false)" class="vxw-btn vxw-btn--ghost vxw-btn--sm">Cancel</button>
                </div>
            @else
                <p class="vxw-hint mt-2">Time is paid from approved Hours Worked. You can link a show or add a reason later, and correct a time with a reason on Timekeeping.</p>
            @endif
        @endif
    </div>
</section>
