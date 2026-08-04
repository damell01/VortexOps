<div class="space-y-4">
    @if($sessions->isEmpty())
    <div class="text-center text-gray-500 dark:text-gray-400 py-8">
        <p>No receiving sessions recorded for this pallet</p>
    </div>
    @else
    <div class="space-y-3">
        @foreach($sessions as $session)
        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-800/50">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-3">
                <div>
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Session ID</p>
                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $session->id }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Operator</p>
                    <p class="text-sm text-gray-900 dark:text-white">{{ $session->user?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Mode</p>
                    <p class="text-sm text-gray-900 dark:text-white">{{ ucfirst($session->mode) }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Items Scanned</p>
                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $session->items_scanned ?? 0 }}</p>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-xs border-t border-gray-200 dark:border-gray-700 pt-3">
                <div>
                    <p class="text-gray-600 dark:text-gray-400">Started</p>
                    <p class="text-gray-900 dark:text-white font-medium">{{ $session->started_at?->format('M d, H:i') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-gray-600 dark:text-gray-400">Ended</p>
                    <p class="text-gray-900 dark:text-white font-medium">{{ $session->ended_at?->format('M d, H:i') ?? 'Ongoing' }}</p>
                </div>
                <div>
                    <p class="text-gray-600 dark:text-gray-400">Status</p>
                    <p class="text-gray-900 dark:text-white font-medium">
                        <span class="inline-block px-2 py-1 text-xs font-semibold rounded {{ $session->isActive() ? 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-100' : 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-100' }}">
                            {{ $session->isActive() ? 'Active' : 'Completed' }}
                        </span>
                    </p>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
