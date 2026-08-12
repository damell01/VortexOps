@props([
    'gradient' => 'from-blue-500 to-blue-600',
    'icon' => '📊',
    'title' => '',
    'description' => '',
    'value' => 0,
    'href' => '#',
])

<div class="group overflow-hidden rounded-xl bg-white dark:bg-gray-900 shadow-lg hover:shadow-2xl transition-all duration-300 hover:scale-105 cursor-pointer transform">
    {{-- Gradient Header --}}
    <div class="bg-gradient-to-r {{ $gradient }} px-6 py-8 text-white relative overflow-hidden">
        {{-- Animated background element --}}
        <div class="absolute inset-0 opacity-0 group-hover:opacity-10 transition-opacity duration-300"
             style="background: radial-gradient(circle at top right, rgba(255,255,255,0.3), transparent)"></div>

        {{-- Content --}}
        <div class="relative">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold">{{ $title }}</h3>
                <span class="text-4xl transform group-hover:scale-110 transition-transform duration-300">{{ $icon }}</span>
            </div>
            <p class="text-sm opacity-90">{{ $description }}</p>
        </div>
    </div>

    {{-- Content Area --}}
    <div class="p-6">
        <div class="flex items-center justify-between mb-4">
            <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ $value }}</span>
            <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-500 transition-colors duration-300"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
        </div>
        <a href="{{ $href }}"
           class="inline-flex items-center gap-2 text-blue-600 dark:text-blue-400 font-medium hover:gap-3 transition-all duration-300">
            {{ $slot }}
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
        </a>
    </div>

    {{-- Animated bottom border --}}
    <div class="h-1 bg-gradient-to-r {{ $gradient }} scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>
</div>
