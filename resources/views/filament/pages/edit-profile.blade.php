<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-start">
            <button
                type="submit"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-500"
            >
                Save Changes
            </button>
        </div>
    </form>
</x-filament-panels::page>
