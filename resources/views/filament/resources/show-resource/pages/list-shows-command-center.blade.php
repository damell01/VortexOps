<x-filament-panels::page>
    @php
        $ops = $this->getOperations();
        $isStreamer = $ops['isStreamer'];
        $workflowService = app(\App\Services\ShowWorkflowService::class);

        $toneClass = fn (string $tone) => match ($tone) {
            'danger', 'red' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-200',
            'success', 'green' => 'bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-200',
            'primary', 'info', 'blue' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-200',
            'purple' => 'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-200',
            'warning', 'amber' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200',
            default => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
        };

        $actionFor = function ($show, array $workflow) use ($isStreamer) {
            if ($isStreamer) {
                $report = $show->streamerLogEntry;
                return [
                    'label' => match (true) {
                        $report?->status === 'changes_requested' => 'Fix Report',
                        (bool) $report?->submitted_at => 'View Report',
                        (bool) $report => 'Resume Report',
                        default => 'Start Report',
                    },
                    'url' => \App\Filament\Pages\EndOfStreamForm::getUrl(['showId' => $show->id]),
                    'tone' => $report?->status === 'changes_requested' ? 'danger' : 'primary',
                ];
            }

            return match ($workflow['key']) {
                'streamer_log' => [
                    'label' => str_contains(strtolower($workflow['label']), 'change') ? 'Resolve Report' : 'Open Report',
                    'url' => \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $show]),
                    'tone' => $workflow['tone'],
                ],
                'admin_review' => [
                    'label' => 'Review Report',
                    'url' => \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $show]),
                    'tone' => 'info',
                ],
                'fulfillment' => [
                    'label' => match ($workflow['label']) {
                        'Ready to Complete' => 'Complete Fulfillment',
                        'Fulfillment Issues' => 'Resolve Fulfillment',
                        'Seal Boxes' => 'Seal Boxes',
                        'Build Boxes' => 'Build Boxes',
                        default => 'Pack Show',
                    },
                    'url' => \App\Filament\Resources\FulfillmentResource::getUrl('view', ['record' => $show]),
                    'tone' => $workflow['tone'],
                ],
                'payroll_review', 'payroll_ready' => [
                    'label' => $workflow['key'] === 'payroll_ready' ? 'Add to Pay Run' : 'Review Payroll',
                    'url' => \App\Filament\Pages\PayrollOverview::getUrl(),
                    'tone' => $workflow['tone'],
                ],
                'payroll' => [
                    'label' => $show->payouts->first()?->batch ? 'Open Pay Run' : 'Open Payroll',
                    'url' => $show->payouts->first()?->batch
                        ? \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view', ['record' => $show->payouts->first()->batch])
                        : \App\Filament\Pages\PayrollOverview::getUrl(),
                    'tone' => 'primary',
                ],
                'paid' => [
                    'label' => 'View Show',
                    'url' => \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $show]),
                    'tone' => 'success',
                ],
                default => [
                    'label' => 'Open Show',
                    'url' => \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $show]),
                    'tone' => 'gray',
                ],
            };
        };

        $renderShowCard = function ($show, bool $attention = false) use ($workflowService, $actionFor, $toneClass) {
            $workflow = $workflowService->stateFor($show);
            $action = $actionFor($show, $workflow);
            $blocker = $workflow['blockers'][0] ?? $workflow['description'];
            $report = $show->streamerLogEntry;
            $packed = $report?->items?->sum('packed_quantity') ?? 0;
            $logged = $report?->items?->sum('quantity') ?? 0;
            $shipments = (int) ($show->shipments_count ?? $show->shipments()->count());
            $buttonTone = match ($action['tone']) {
                'danger' => 'bg-red-600 hover:bg-red-500',
                'success' => 'bg-green-600 hover:bg-green-500',
                'warning' => 'bg-amber-600 hover:bg-amber-500',
                'purple' => 'bg-purple-600 hover:bg-purple-500',
                default => 'bg-primary-600 hover:bg-primary-500',
            };
        @endphp
            <article class="border-b border-gray-100 p-4 last:border-0 dark:border-gray-800 sm:p-5 {{ $attention ? 'bg-amber-50/20 dark:bg-amber-950/5' : '' }}">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $show]) }}" class="truncate text-sm font-bold text-gray-950 hover:text-primary-600 dark:text-white sm:text-base">{{ $show->title ?: 'Show #'.$show->id }}</a>
                            <span class="rounded-full px-2 py-1 text-[10px] font-semibold sm:text-xs {{ $toneClass($workflow['tone']) }}">{{ $workflow['label'] }}</span>
                        </div>
                        <div class="mt-1 flex flex-wrap gap-x-2 gap-y-1 text-[10px] text-gray-500 dark:text-gray-400 sm:text-xs">
                            <span>{{ $show->show_date?->format('M j, Y') }}</span>
                            @if($show->start_time)<span>· {{ $show->start_time->format('g:i A') }}</span>@endif
                            @if($show->channel)<span>· {{ $show->channel->name }}</span>@endif
                            <span>· {{ $show->streamers->pluck('name')->join(', ') ?: 'No streamer' }}</span>
                        </div>
                        <div class="mt-2 text-xs font-medium {{ $workflow['blockers'] ? 'text-amber-700 dark:text-amber-300' : 'text-gray-600 dark:text-gray-300' }}">{{ $blocker }}</div>
                        <div class="mt-2 flex flex-wrap gap-3 text-[10px] text-gray-500 dark:text-gray-400 sm:text-xs">
                            <span>{{ number_format($shipments) }} shipment(s)</span>
                            @if($logged > 0)<span>{{ number_format($packed) }}/{{ number_format($logged) }} packed</span>@endif
                            <span>Fulfillment: {{ $show->fulfillmentUsers->pluck('name')->join(', ') ?: 'Unassigned' }}</span>
                            @if($show->gross_revenue !== null)<span>${{ number_format((float)$show->gross_revenue, 2) }} sales</span>@endif
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2 lg:w-52 lg:justify-end">
                        <a href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $show]) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-3 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">Details</a>
                        <a href="{{ $action['url'] }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg px-3 text-center text-xs font-bold text-white lg:flex-none {{ $buttonTone }}">{{ $action['label'] }}</a>
                    </div>
                </div>
            </article>
        @php
        };
    @endphp

    <div class="space-y-4" data-vx-page="shows-command-center">
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="p-4 sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-[.12em] text-primary-600 sm:text-xs">{{ $isStreamer ? 'My Shows' : 'Shows Command Center' }}</div>
                        <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white sm:text-xl">{{ $isStreamer ? 'Your schedule and show reports' : 'One place to move every show forward' }}</h2>
                        <p class="mt-1 max-w-3xl text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">{{ $isStreamer ? 'Open the next report task directly from each show.' : 'Each show has one workflow stage and one recommended next action from streamer report through fulfillment and payroll.' }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-[10px] sm:text-xs">
                        <span class="rounded-full bg-blue-50 px-2.5 py-1 font-semibold text-blue-700 dark:bg-blue-950/40 dark:text-blue-200">{{ $ops['upcoming']->count() }} upcoming</span>
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $ops['recent']->count() }} recent</span>
                        @unless($isStreamer)<span class="rounded-full bg-amber-50 px-2.5 py-1 font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-200">{{ $ops['needsAttention']->count() }} attention</span>@endunless
                    </div>
                </div>
            </div>
        </section>

        @unless($isStreamer)
            <section class="overflow-hidden rounded-2xl border border-amber-200 bg-white dark:border-amber-900/70 dark:bg-gray-900">
                <div class="border-b border-amber-100 bg-amber-50/60 px-4 py-3 dark:border-amber-900/40 dark:bg-amber-950/20 sm:px-5">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Do These Next</h3>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Shows with blockers or operational work that should be handled first.</p>
                </div>
                @forelse($ops['needsAttention'] as $show)
                    @php $renderShowCard($show, true); @endphp
                @empty
                    <div class="p-8 text-center"><x-heroicon-o-check-circle class="mx-auto h-8 w-8 text-green-500"/><div class="mt-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Nothing urgent right now</div></div>
                @endforelse
            </section>
        @endunless

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-5">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">{{ $isStreamer ? 'My Upcoming Shows' : 'Upcoming Schedule' }}</h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Future shows stay simple until post-show work begins.</p>
            </div>
            @forelse($ops['upcoming'] as $show)
                @php $renderShowCard($show); @endphp
            @empty
                <div class="p-8 text-center text-sm text-gray-500">No upcoming shows.</div>
            @endforelse
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-5">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">{{ $isStreamer ? 'Recent Shows & Reports' : 'Recent Shows — Workflow' }}</h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Current workflow stage, blocker, and next action are visible without opening the show first.</p>
            </div>
            @forelse($ops['recent'] as $show)
                @php $renderShowCard($show); @endphp
            @empty
                <div class="p-8 text-center text-sm text-gray-500">No recent shows.</div>
            @endforelse
        </section>

        <details class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-700 dark:text-gray-200 sm:px-5">Advanced show table / filters</summary>
            <div class="border-t border-gray-100 p-3 dark:border-gray-800 sm:p-4">{{ $this->table }}</div>
        </details>
    </div>
</x-filament-panels::page>
