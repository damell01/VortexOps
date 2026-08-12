@php
    use App\Models\Show;

    /** @var Show $record */
    $record = $this->record;
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Header -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                        {{ $record->title ?? 'Show' }}
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400">
                        {{ $record->primaryStreamer()?->name ?? 'Unknown Streamer' }} • {{ $record->show_date?->format('M d, Y') }}
                    </p>
                </div>
                <div class="text-right">
                    <span @class([
                        'inline-block px-4 py-2 rounded-full font-medium text-sm',
                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' => in_array($record->status, ['draft', 'pending_review']),
                        'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' => $record->status === 'mapping',
                        'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' => $record->status === 'pending_approval',
                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' => in_array($record->status, ['reconciled', 'closed']),
                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' => $record->status === 'cancelled',
                    ])>
                        {{ Show::statusLabels()[$record->status] ?? ucfirst(str_replace('_', ' ', $record->status)) }}
                    </span>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Gross Revenue</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">${{ number_format((float) $record->gross_revenue, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Items Count</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $record->orders()->count() }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Units Sold</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $record->units_sold ?? 0 }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Shipments</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $record->shipments()->count() }}</p>
                </div>
            </div>
        </div>

        <!-- Fulfillment Dashboard -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            @livewire('fulfillment-dashboard', [
                'show' => $record,
            ], key('fulfillment-' . $record->id))
        </div>

        <!-- Actions -->
        <div class="flex gap-3">
            @foreach($this->getHeaderActions() as $action)
                {{ $action }}
            @endforeach
            <a href="{{ route('filament.admin.resources.fulfillment-center.index') }}" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition font-medium">
                ← Back
            </a>
        </div>
    </div>
</x-filament-panels::page>
