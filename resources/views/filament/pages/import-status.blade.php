<x-filament-panels::page>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-2xl text-sm text-gray-500">Channel sync history and records still waiting for analytics. Counts below cover the last 24 hours; successful record updates are separate from completed runs.</p>
        <button type="button" wire:click="$refresh" wire:loading.attr="disabled" class="vx-btn">Refresh status</button>
    </div>
    <div class="grid gap-4 xl:grid-cols-2">
        @forelse($this->channels as $row)
            <section class="vx-card min-w-0 p-4 sm:p-5">
                <header class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-bold">{{ $row['channel']->name }}</h2>
                    <span class="text-xs text-gray-500">{{ $row['channel']->include_in_import ? 'Included in imports' : 'Channel import flag off' }}</span>
                </header>
                <dl class="my-4 space-y-2 text-sm">
                    <div><dt class="text-gray-500">Last successful run</dt><dd class="font-semibold">{{ $row['run']?->finished_at?->format('M j, Y g:i A') ?? 'No completed run recorded' }}</dd></div>
                    <div><dt class="text-gray-500">Last successful record update</dt><dd>{{ $row['success']?->created_at?->format('M j, Y g:i A') ?? 'No update recorded' }}</dd></div>
                </dl>
                <div class="vx-import-numbers grid grid-cols-3 gap-2 rounded-xl bg-gray-50 p-3 dark:bg-gray-800">
                    @foreach(['updates'=>'Updates · 24h','failures'=>'Failed / partial · 24h','missing_count'=>'Awaiting analytics'] as $key=>$label)
                        <div class="min-w-0"><div class="text-xl font-bold">{{ number_format($row[$key]) }}</div><div class="text-xs text-gray-500">{{ $label }}</div></div>
                    @endforeach
                </div>
                <details class="mt-4" @if($row['missing_count']) open @endif>
                    <summary class="cursor-pointer py-2 text-sm font-semibold">Records needing attention</summary>
                    <div class="space-y-2">
                        @forelse($row['missing'] as $show)
                            <a class="block rounded-xl border border-gray-200 p-3 text-sm dark:border-gray-700" href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record'=>$show]) }}">
                                <span class="block font-semibold">{{ $show->title ?: 'Show #'.$show->id }}</span>
                                <span class="text-xs text-gray-500">{{ $show->show_date?->format('M j') }} · {{ $show->analytics_sync_note ?: 'Analytics still incomplete; awaiting a scheduled retry.' }}</span>
                            </a>
                        @empty <p class="py-2 text-sm text-gray-500">No past shows awaiting analytics.</p> @endforelse
                        @if($row['missing_count'] > 5)<p class="text-xs text-gray-500">Showing the 5 most recent of {{ $row['missing_count'] }} records.</p>@endif
                    </div>
                </details>
                <details class="mt-2">
                    <summary class="cursor-pointer py-2 text-sm font-semibold">What changed · latest events</summary>
                    <div class="space-y-2">
                        @forelse($row['events'] as $event)
                            <article class="rounded-xl border border-gray-200 p-3 text-sm dark:border-gray-700">
                                <div class="flex flex-wrap justify-between gap-2"><strong>{{ $event->show?->title ?: $event->sourceLabel() }}</strong><span class="text-xs text-gray-500">{{ $event->created_at->format('g:i A') }} · {{ ucfirst($event->status) }}</span></div>
                                <p class="mt-1">{{ $event->summary() }}</p>
                                @if($event->show)<a class="mt-2 inline-block font-semibold text-primary-600" href="{{ \App\Filament\Resources\ShowResource::getUrl('view',['record'=>$event->show]) }}">Review show</a>@endif
                                @if($event->status === 'success')
                                    <dl class="mt-2 space-y-1 text-xs text-gray-500">@foreach(array_slice($event->capturedFields(),0,6,true) as $field=>$value)<div><dt class="inline font-semibold">{{ $field }}:</dt> <dd class="inline">{{ $value }}</dd></div>@endforeach</dl>
                                @endif
                            </article>
                        @empty <p class="py-2 text-sm text-gray-500">No events recorded in the last 24 hours.</p> @endforelse
                    </div>
                </details>
            </section>
        @empty <p class="text-sm text-gray-500">No channels available in this channel selection.</p> @endforelse
    </div>
</x-filament-panels::page>
