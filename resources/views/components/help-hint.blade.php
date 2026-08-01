@props([
    'icon' => '💡',
    'title' => '',
    'dismissible' => true,
])

<div class="rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 p-4 animate-fade-in"
     x-data="{ dismissed: false }"
     x-show="!dismissed"
     @click.away="dismissed = true"
>
    <div class="flex gap-3">
        <div class="flex-shrink-0 text-2xl">{{ $icon }}</div>
        <div class="flex-grow">
            @if($title)
                <h3 class="font-semibold text-blue-900 dark:text-blue-200 mb-1">{{ $title }}</h3>
            @endif
            <div class="text-sm text-blue-800 dark:text-blue-300">
                {{ $slot }}
            </div>
        </div>
        @if($dismissible)
            <button
                @click="dismissed = true"
                class="flex-shrink-0 text-blue-400 hover:text-blue-600 dark:hover:text-blue-300 transition"
                aria-label="Dismiss"
            >
                ✕
            </button>
        @endif
    </div>
</div>

<style>
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-4px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-fade-in {
        animation: fadeIn 300ms ease-out;
    }
</style>
