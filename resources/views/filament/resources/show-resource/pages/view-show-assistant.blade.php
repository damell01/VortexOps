<x-filament-panels::page>
    @php($show = $this->record)
    <section class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:p-6">
        <a class="text-sm font-semibold text-primary-600" href="{{ \App\Filament\Resources\ShowResource::getUrl() }}">← All shows</a>
        <h1 class="mt-3 text-2xl font-bold">{{ $show->title ?: 'Show #'.$show->id }}</h1>
        <dl class="mt-5 grid gap-4 sm:grid-cols-2">
            @foreach([
                'Date' => $show->show_date?->format('M j, Y'),
                'Start time' => $show->start_time?->format('g:i A'),
                'Channel' => $show->channel?->name,
                'Streamers' => $show->streamers->pluck('name')->join(', '),
                'Status' => ucfirst(str_replace('_', ' ', $show->status ?? 'unknown')),
            ] as $label => $value)
                <div><dt class="text-xs text-gray-500">{{ $label }}</dt><dd class="mt-1 text-sm font-semibold">{{ $value ?: 'Not set' }}</dd></div>
            @endforeach
        </dl>
    </section>
</x-filament-panels::page>
