{{-- Staging moved onto the pallet's own page; this route only redirects there.
     Rendered solely if the redirect is somehow not followed. --}}
<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Staging is now worked on the pallet page.
        <a href="{{ \App\Filament\Resources\PalletResource::getUrl('view', ['record' => $this->record]) }}"
           class="text-primary-600 dark:text-primary-400 underline">
            Open pallet
        </a>
    </p>
</x-filament-panels::page>
