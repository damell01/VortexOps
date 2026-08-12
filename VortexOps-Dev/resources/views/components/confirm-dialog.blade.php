@props([
    'icon' => '❓',
    'title' => 'Confirm Action',
    'description' => 'Are you sure you want to continue?',
    'confirmText' => 'Confirm',
    'cancelText' => 'Cancel',
    'isDangerous' => false,
])

{{-- Enhanced Confirmation Dialog Component --}}
<div class="space-y-6 animate-fade-in">
    {{-- Icon and Title --}}
    <div class="text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full
                  {{ $isDangerous ? 'bg-red-100 dark:bg-red-900/30' : 'bg-blue-100 dark:bg-blue-900/30' }} mb-4">
            <span class="text-4xl">{{ $icon }}</span>
        </div>
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">{{ $title }}</h2>
        <p class="text-gray-600 dark:text-gray-400">{{ $description }}</p>
    </div>

    {{-- Warning content if dangerous --}}
    @if($isDangerous && isset($consequences))
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
            <h3 class="font-semibold text-red-900 dark:text-red-200 mb-2">This action cannot be undone:</h3>
            <ul class="list-disc list-inside space-y-1 text-sm text-red-800 dark:text-red-300">
                @foreach($consequences as $consequence)
                    <li>{{ $consequence }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Slot for additional content --}}
    @if($slot->isNotEmpty())
        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4">
            {{ $slot }}
        </div>
    @endif

    {{-- Action Buttons --}}
    <div class="flex gap-3 justify-end">
        <button type="button"
                onclick="this.closest('[role=dialog]')?.close()"
                class="px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition">
            {{ $cancelText }}
        </button>
        <button type="button"
                onclick="this.closest('[role=dialog]')?.dispatchEvent(new CustomEvent('confirm'))"
                class="px-4 py-2.5 rounded-lg font-medium text-white transition flex items-center gap-2
                       {{ $isDangerous
                           ? 'bg-red-500 hover:bg-red-600'
                           : 'bg-blue-500 hover:bg-blue-600' }}">
            {{ $confirmText }}
        </button>
    </div>

    {{-- Accessibility note --}}
    <p class="text-xs text-gray-500 dark:text-gray-400 text-center">
        Press <kbd class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs font-mono border border-gray-300 dark:border-gray-700">Enter</kbd>
        to confirm or <kbd class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs font-mono border border-gray-300 dark:border-gray-700">Esc</kbd>
        to cancel
    </p>
</div>

<script>
    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
        const dialog = e.target.closest('[role=dialog]');
        if (!dialog) return;

        if (e.key === 'Enter') {
            dialog.dispatchEvent(new CustomEvent('confirm'));
        }
        if (e.key === 'Escape') {
            dialog.close?.();
        }
    });
</script>
