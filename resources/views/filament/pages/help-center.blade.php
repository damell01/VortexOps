<x-filament-panels::page>
    <div class="space-y-5">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-xl font-bold">Your guides, ready when you need them</h2>
            <p class="mt-2 text-sm text-gray-500">Choose a PDF guide to read here, open it in your browser, or download a copy. The guides below follow your account's access.</p>
        </section>
        <section aria-labelledby="pdf-guides-heading">
            <h2 id="pdf-guides-heading" class="mb-3 text-lg font-bold">PDF guides</h2>
            <div class="grid gap-4 md:grid-cols-3">
                @forelse($documents as $id => $doc)
                    <article class="rounded-2xl border bg-white p-5 dark:bg-gray-900 {{ $selectedGuide === $id ? 'border-primary-400 ring-1 ring-primary-400' : 'border-gray-200 dark:border-gray-700' }}">
                        <div class="flex items-center gap-3"><x-heroicon-o-document-text class="h-6 w-6 shrink-0 text-primary-500"/><h3 class="font-bold">{{ $doc['title'] }}</h3></div>
                        <p class="mt-3 text-sm text-gray-500">{{ $doc['description'] }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-filament::button type="button" wire:click="selectGuide('{{ $id }}')" wire:loading.attr="disabled" wire:target="selectGuide" :aria-pressed="$selectedGuide === $id ? 'true' : 'false'">{{ $selectedGuide === $id ? 'Reading now' : 'Read here' }}</x-filament::button>
                            <x-filament::button color="gray" tag="a" :href="route('admin.guides.show', ['guide'=>$id,'download'=>1])">Download</x-filament::button>
                        </div>
                    </article>
                @empty
                    <p class="text-sm text-gray-500">A dedicated PDF for your role has not been added yet. Ask an admin for the guide you need.</p>
                @endforelse
            </div>
        </section>
        @if($selectedDocument)
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" aria-label="Guide reader">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 p-4 dark:border-gray-700">
                <div><h2 class="font-bold">{{ $selectedDocument['title'] }}</h2><p class="mt-1 text-xs text-gray-500">Use the PDF toolbar to change pages or zoom.</p></div>
                <div class="flex flex-wrap gap-2">
                    <x-filament::button color="gray" tag="a" :href="route('admin.guides.show',$selectedGuide)" target="_blank" rel="noopener">Open in browser</x-filament::button>
                    <x-filament::button tag="a" :href="route('admin.guides.show',['guide'=>$selectedGuide,'download'=>1])" icon="heroicon-o-arrow-down-tray">Download PDF</x-filament::button>
                </div>
            </div>
            <div wire:key="pdf-reader-{{ $selectedGuide }}">
                <iframe src="{{ route('admin.guides.show',$selectedGuide) }}" title="{{ $selectedDocument['title'] }} PDF" class="w-full border-0" style="height:75vh;min-height:420px" loading="lazy"></iframe>
            </div>
            <p class="border-t border-gray-200 p-4 text-xs text-gray-500 dark:border-gray-700">If your device does not display the PDF here, choose Open in browser or Download PDF.</p>
        </section>
        @endif
        <div class="rounded-2xl border border-gray-200 p-5 dark:border-gray-700">
            <h2 class="font-bold">More reference help</h2>
            <div class="mt-3 flex flex-wrap gap-4 text-sm font-semibold text-primary-600">
                @if(\App\Filament\Pages\InventoryGuide::canAccess())<a href="{{ \App\Filament\Pages\InventoryGuide::getUrl(['tab'=>'tasks']) }}">Inventory workflows in the app</a>@endif
                @if(\App\Filament\Pages\HowItWorks::canAccess())<a href="{{ \App\Filament\Pages\HowItWorks::getUrl(panel:'admin') }}">Your role & workflow</a>@endif
                @if(\App\Filament\Pages\Handbook::canAccess() && ! \App\Support\NavVisibility::isHiddenForUser(\App\Filament\Pages\Handbook::class, auth()->user()))<a href="{{ \App\Filament\Pages\Handbook::getUrl(panel:'admin') }}?module={{ auth()->user()?->isFulfillment() ? 'fulfillment' : 'inventory' }}">Operations handbook</a>@endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
