@props([
    'label' => '',
    'hint' => '',
    'error' => '',
    'required' => false,
])

<div class="mb-6 animate-fade-in-up">
    <div class="flex items-end justify-between gap-2 mb-2">
        <label class="block text-sm font-medium text-gray-900 dark:text-white">
            {{ $label }}
            @if($required)
                <span class="text-red-500">*</span>
            @endif
        </label>

        @if($hint)
            <div class="group relative">
                <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div class="absolute bottom-full right-0 mb-2 w-48 px-3 py-2 text-xs text-white bg-gray-900 dark:bg-gray-700 rounded-lg shadow-lg opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-20">
                    {{ $hint }}
                    <div class="absolute top-full right-3 border-4 border-transparent border-t-gray-900 dark:border-t-gray-700"></div>
                </div>
            </div>
        @endif
    </div>

    {{-- Form field slot --}}
    <div class="relative">
        {{ $slot }}

        {{-- Focus indicator --}}
        <div class="absolute inset-0 rounded-lg pointer-events-none opacity-0 ring-2 ring-blue-500 ring-offset-2 dark:ring-offset-gray-900 transition-opacity duration-200"></div>
    </div>

    {{-- Error message --}}
    @if($error)
        <div class="mt-2 flex items-center gap-1 text-sm text-red-600 dark:text-red-400 animate-fade-in">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
            {{ $error }}
        </div>
    @endif
</div>

<style>
    @media (max-width: 768px) {
        .form-field {
            margin-bottom: 1.5rem;
        }

        .form-field label {
            font-size: 0.875rem;
        }

        .form-field input,
        .form-field select,
        .form-field textarea {
            font-size: 16px; /* Prevent iOS zoom */
        }
    }
</style>
