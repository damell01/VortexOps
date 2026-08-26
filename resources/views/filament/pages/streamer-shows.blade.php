@php
    $groups = $this->groups();

    $tones = [
        'danger'  => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300',
        'warning' => 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
        'info'    => 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300',
        'success' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
        'gray'    => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
    ];
@endphp

<x-filament-panels::page>
    <div class="space-y-3 sm:space-y-5" data-vx-page="streamer-shows">

        @php $needsYou = $groups['needs_you']; @endphp

        {{-- Sorted by who is blocked, not by date: a show from three weeks ago
             that still has no report is more urgent than last night's. --}}
        <section @class([
            'overflow-hidden rounded-xl border sm:rounded-2xl',
            'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30' => filled($needsYou),
            'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900' => empty($needsYou),
        ])>
            <div class="p-4 sm:p-5">
                <div @class([
                    'text-[10px] font-bold uppercase tracking-[.12em] sm:text-xs',
                    'text-amber-700 dark:text-amber-400' => filled($needsYou),
                    'text-emerald-700 dark:text-emerald-400' => empty($needsYou),
                ])>Waiting on you</div>
                <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white sm:text-xl">
                    @if (filled($needsYou))
                        {{ count($needsYou) }} {{ Str::plural('show', count($needsYou)) }} {{ count($needsYou) === 1 ? 'needs' : 'need' }} a report
                    @else
                        Nothing needs you right now
                    @endif
                </h2>
                @if (empty($needsYou))
                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">Every show you have run has a report filed. New ones appear here the morning after.</p>
                @endif
            </div>

            @if (filled($needsYou))
                <div class="divide-y divide-amber-200 bg-amber-200 dark:divide-amber-900 dark:bg-amber-900 sm:grid sm:grid-cols-2 sm:gap-px sm:divide-y-0">
                    @foreach ($needsYou as $show)
                        <a href="{{ $show['url'] }}" style="display:block !important"
                           class="group bg-white p-4 transition hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800/70">
                          <div class="w-full min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $show['title'] }}</div>
                                    <div class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                                        {{ $show['date'] }}@if ($show['channel']) · {{ $show['channel'] }}@endif
                                    </div>
                                </div>
                                <span class="shrink-0 whitespace-nowrap rounded-full {{ $tones[$show['tone']] }} px-2 py-0.5 text-[10px] font-bold">{{ $show['state'] }}</span>
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-gray-500 dark:text-gray-400">
                                <span class="whitespace-nowrap">{{ number_format($show['shipments']) }} {{ Str::plural('shipment', $show['shipments']) }}</span>
                                @if ($show['slow_pack'])
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">Slow to pack</span>
                                @endif
                            </div>

                            <div class="mt-3 inline-flex min-h-9 w-fit items-center gap-1.5 self-start rounded-lg bg-primary-600 px-3 text-xs font-semibold text-white">
                                {{ $show['action'] }}
                                <x-heroicon-m-arrow-right class="h-4 w-4" />
                            </div>
                          </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        @foreach ([
            ['key' => 'upcoming', 'title' => 'Coming up',       'blurb' => 'Shows you are scheduled to run. Nothing to do until they have aired.'],
            ['key' => 'waiting',  'title' => 'With the office',  'blurb' => 'Filed and waiting on review. You will see these move back up if changes are asked for.'],
            ['key' => 'done',     'title' => 'Done',             'blurb' => 'Approved. Kept here so you can look back at what you reported.'],
        ] as $section)
            @php $rows = $groups[$section['key']]; @endphp

            @if (filled($rows))
                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
                    <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-5">
                        <h2 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">{{ $section['title'] }} <span class="ml-1 text-gray-400">{{ count($rows) }}</span></h2>
                        <p class="mt-0.5 text-[11px] leading-4 text-gray-500 dark:text-gray-400 sm:text-xs">{{ $section['blurb'] }}</p>
                    </div>

                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($rows as $show)
                            @php $tag = 'div'; @endphp
                            <{{ $show['url'] ? 'a' : 'div' }}
                                style="display:block !important"
                                @if ($show['url']) href="{{ $show['url'] }}" @endif
                                @class([
                                    'block px-4 py-3 sm:px-5',
                                    'transition hover:bg-gray-50 dark:hover:bg-gray-800/60' => (bool) $show['url'],
                                ])
                            >
                              <div class="flex w-full min-w-0 flex-wrap items-center gap-x-4 gap-y-2">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $show['title'] }}</div>
                                    <div class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                                        {{ $show['date'] }}@if ($show['time']) · {{ $show['time'] }}@endif
                                        @if ($show['channel']) · {{ $show['channel'] }}@endif
                                    </div>
                                </div>

                                <div class="shrink-0 whitespace-nowrap text-[11px] text-gray-500 dark:text-gray-400">
                                    {{ number_format($show['shipments']) }} {{ Str::plural('shipment', $show['shipments']) }}
                                </div>

                                <span class="shrink-0 whitespace-nowrap rounded-full {{ $tones[$show['tone']] }} px-2 py-0.5 text-[10px] font-bold">{{ $show['state'] }}</span>

                                @if ($show['url'])
                                    <span class="shrink-0 whitespace-nowrap text-xs font-semibold text-primary-600 dark:text-primary-400">{{ $show['action'] }} →</span>
                                @endif
                              </div>

                              {{-- Filed, past the edit window, and something is
                                   wrong with it. Before this the only route was
                                   to find an admin in person. --}}
                              @if ($show['can_request_revision'] && $revisionFor !== $show['id'])
                                  <button type="button" wire:click.prevent="askForChanges({{ $show['id'] }})"
                                          class="vx-plain mt-2 text-[11px] font-medium text-gray-500 underline-offset-2 hover:underline dark:text-gray-400">
                                      Need to change something?
                                  </button>
                              @endif

                              @if ($revisionFor === $show['id'])
                                  <div wire:click.prevent="" class="mt-2 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/60">
                                      <label class="block text-[11px] font-semibold text-gray-700 dark:text-gray-200">
                                          What needs changing?
                                      </label>
                                      <textarea wire:model="revisionReason" rows="2"
                                                placeholder="e.g. I logged 3 of the wrong box"
                                                class="mt-1.5 w-full rounded-lg border-gray-300 text-xs dark:border-gray-600 dark:bg-gray-800"></textarea>
                                      <div class="mt-2 flex items-center gap-2">
                                          <button type="button" wire:click.prevent="submitRevisionRequest"
                                                  class="vx-plain rounded-lg bg-primary-600 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-primary-500">
                                              Ask an admin to reopen it
                                          </button>
                                          <button type="button" wire:click.prevent="cancelRevisionRequest"
                                                  class="vx-plain text-[11px] text-gray-500 hover:underline dark:text-gray-400">
                                              Cancel
                                          </button>
                                      </div>
                                  </div>
                              @endif

                              @if ($show['revision_requested'])
                                  <p class="mt-2 text-[11px] leading-4 text-blue-700 dark:text-blue-300">
                                      {{-- No inline @ if here: Blade leaves a directive that
                                           directly follows a word character as literal text, and
                                           its @ endif then compiles alone. --}}
                                      You asked for this one to be reopened{{ $show['revision_reason'] ? ' — “' . $show['revision_reason'] . '”' : '' }}.
                                      It stays as filed until an admin reopens it.
                                  </p>
                              @endif
                            </{{ $show['url'] ? 'a' : 'div' }}>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach

        @if (empty($groups['needs_you']) && empty($groups['upcoming']) && empty($groups['waiting']) && empty($groups['done']))
            <section class="rounded-xl border border-gray-200 bg-white p-8 text-center dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
                <x-heroicon-o-video-camera class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <h2 class="mt-3 text-base font-semibold text-gray-950 dark:text-white">No shows yet</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Once you are assigned to a show it will appear here, and you will be able to file its report from this page.</p>
            </section>
        @endif
    </div>
</x-filament-panels::page>
