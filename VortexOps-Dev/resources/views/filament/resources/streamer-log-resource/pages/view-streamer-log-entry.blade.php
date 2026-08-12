@php
    use App\Models\StreamerLogEntry;

    /** @var StreamerLogEntry $record */
    $record = $this->record;
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Header -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                        {{ $record->show?->title ?? 'Streamer Log' }}
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400">
                        {{ $record->streamer?->name ?? 'Unknown Streamer' }} • {{ $record->show?->show_date?->format('M d, Y') }}
                    </p>
                </div>
                <div class="text-right">
                    <span @class([
                        'inline-block px-4 py-2 rounded-full font-medium text-sm',
                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' => !$record->reviewed_at,
                        'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' => $record->reviewed_at && $record->status === 'streamer_reviewed',
                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' => $record->status === 'admin_approved',
                    ])>
                        @switch($record->status)
                            @case('pending')
                                🔄 Pending
                                @break
                            @case('streamer_reviewed')
                                👀 Awaiting Admin
                                @break
                            @case('admin_approved')
                                ✓ Approved
                                @break
                            @default
                                {{ $record->status }}
                        @endswitch
                    </span>
                </div>
            </div>
        </div>

        <!-- Tabbed Interface -->
        @livewire('streamer-log-tabs', [
            'record' => $record,
        ], key('tabs-' . $record->id))

        <!-- Action Links -->
        <div class="flex gap-3">
            <a href="{{ route('filament.admin.resources.streamer-logs.edit', $record) }}" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition font-medium">
                ✏️ Edit
            </a>
            <a href="{{ route('filament.admin.resources.streamer-logs.index') }}" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition font-medium">
                ← Back
            </a>
        </div>
    </div>
</x-filament-panels::page>
