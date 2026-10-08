<x-filament-panels::page>
<div class="space-y-5" data-vx-page="report-inbox">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h2 class="text-xl font-bold">Show report inbox</h2><p class="mt-1 text-sm text-gray-500">{{ $this->reportStartDate() ? 'Reports for shows from ' . $this->reportStartDate() . ' onward.' : 'All reporting dates. Set the report start date in Settings → Post-Show Workflow to exclude historical reports.' }} Counts follow the selected channel.</p></div>
        <button type="button" wire:click="$refresh" class="min-h-11 rounded-xl border border-gray-300 px-4 text-sm font-semibold dark:border-gray-600">Refresh inbox</button>
    </div>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5" aria-label="Filter reports by status">
        @foreach($this->getStats() as $key => $stat)
            <button type="button" wire:click="selectInboxStatus('{{ $key }}')" aria-pressed="{{ $activeTab === $key ? 'true' : 'false' }}" class="min-h-32 rounded-2xl border bg-white p-4 text-left transition hover:border-primary-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 dark:bg-gray-900 {{ $activeTab === $key ? 'border-primary-500 ring-2 ring-primary-500/20' : 'border-gray-200 dark:border-gray-700' }}">
                <div class="flex items-center justify-between gap-2"><span class="text-sm font-semibold">{{ $stat['label'] }}</span><x-filament::icon :icon="$stat['icon']" class="h-5 w-5 text-gray-400"/></div>
                <div class="mt-2 text-2xl font-bold">{{ $stat['value'] }}</div><p class="mt-1 text-xs text-gray-500">{{ $stat['sub'] }}</p>
            </button>
        @endforeach
    </div>
    @if(auth()->user()?->isAdmin())
        <section class="rounded-2xl border border-amber-200 bg-white p-4 dark:border-amber-900 dark:bg-gray-900" aria-labelledby="reopen-heading">
            <div class="flex flex-wrap items-center justify-between gap-2"><h3 id="reopen-heading" class="font-bold">Requests to reopen</h3><button type="button" wire:click="selectInboxStatus('edit_requested')" class="min-h-11 px-3 text-sm font-semibold text-primary-600">View all requests →</button></div>
            <div class="mt-3 grid gap-3 lg:grid-cols-2">
                @forelse($this->reopenRequests() as $report)
                    <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-700" wire:key="reopen-request-{{ $report->id }}">
                        <h4 class="font-semibold">{{ $report->show?->title ?: 'Report #' . $report->id }}</h4><p class="mt-1 text-xs text-gray-500">{{ $report->streamer?->name ?: 'No streamer linked' }} · {{ $report->show?->show_date?->format('M j, Y') ?: 'No show date' }} · Requested {{ $report->revision_requested_at?->diffForHumans() }}</p>
                        <p class="mt-3 whitespace-pre-line text-sm">{{ $report->revision_reason ?: 'The streamer did not include a reason.' }}</p>
                        <div class="mt-4 flex flex-wrap gap-2"><a href="{{ \App\Filament\Resources\StreamerLogResource::getUrl('edit', ['record' => $report]) }}" class="inline-flex min-h-11 items-center rounded-xl border border-gray-300 px-3 text-sm font-semibold dark:border-gray-600">Review report</a><button type="button" wire:click="reopenRequestedReport({{ $report->id }})" wire:confirm="Reopen this report for the streamer? Existing inventory postings remain in place." wire:loading.attr="disabled" wire:target="reopenRequestedReport" class="min-h-11 rounded-xl bg-amber-500 px-3 text-sm font-semibold text-gray-950">Approve & reopen</button></div>
                    </article>
                @empty
                    <p class="py-3 text-sm text-gray-500">No pending edit requests in this reporting period and channel.</p>
                @endforelse
            </div>
        </section>
    @endif
    <div class="min-w-0 rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">{{ $this->table }}</div>
</div>
</x-filament-panels::page>
