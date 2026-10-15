<x-filament-panels::page>
    <div class="space-y-5">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-xl font-bold">Your guides, ready when you need them</h2>
            <p class="mt-2 text-sm text-gray-500">Choose a PDF guide to read here, open it in your browser, or download a copy. The guides below follow your account's access.</p>
        </section>
        @php
            $isAdmin = auth()->user()?->isAdmin() || auth()->user()?->isOwner();
            $setupGuides = collect($isAdmin ? ['guide.payroll', 'guide.estill', 'guide.inventory', 'guide.streamer', 'guide.shows'] : ['guide.shows'])
                ->mapWithKeys(fn ($key) => [$key => \App\Support\HelpTopics::get($key)])->filter();
        @endphp
        <section aria-labelledby="setup-guides-heading">
            <h2 id="setup-guides-heading" class="mb-1 text-lg font-bold">Setup guides</h2>
            <p class="mb-3 text-sm text-gray-500">Short, step-by-step. Every page also has "How this page works" beside its title.</p>
            <div class="grid gap-3 md:grid-cols-3">
                @foreach($setupGuides as $key => $guide)
                    <button type="button" data-vx-help="{{ $key }}" class="rounded-2xl border border-gray-200 bg-white p-4 text-left transition hover:border-primary-400 hover:shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <span class="flex items-center gap-2 font-bold"><x-heroicon-o-light-bulb class="h-5 w-5 shrink-0 text-primary-500"/>{{ $guide['title'] }}</span>
                        <span class="mt-2 block text-sm text-gray-500">{{ $guide['intro'] }}</span>
                    </button>
                @endforeach
            </div>
        </section>
        <section aria-labelledby="pdf-guides-heading">
            <h2 id="pdf-guides-heading" class="mb-3 text-lg font-bold">PDF guides</h2>
            <div class="grid gap-4 md:grid-cols-3">
                @forelse($documents as $id => $doc)
                    <article class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex items-center gap-3"><x-heroicon-o-document-text class="h-6 w-6 shrink-0 text-primary-500"/><h3 class="font-bold">{{ $doc['title'] }}</h3></div>
                        <p class="mt-3 text-sm text-gray-500">{{ $doc['description'] }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-filament::button type="button" wire:click="selectGuide('{{ $id }}')" wire:loading.attr="disabled" wire:target="selectGuide" icon="heroicon-o-book-open">Read here</x-filament::button>
                            <x-filament::button color="gray" tag="a" :href="route('admin.guides.show', ['guide'=>$id,'download'=>1])">Download</x-filament::button>
                        </div>
                    </article>
                @empty
                    <p class="text-sm text-gray-500">A dedicated PDF for your role has not been added yet. Ask an admin for the guide you need.</p>
                @endforelse
            </div>
        </section>
        @if($selectedDocument)
        <div wire:key="guide-reader-{{ $selectedGuide }}" x-data="{ view: 'flip' }" x-trap.noscroll="true" x-on:keydown.escape="$wire.closeReader()"
            class="vx-guide-overlay fixed inset-0 flex flex-col" style="z-index:100;background:radial-gradient(ellipse at top,#2a1b4d 0%,#120c26 70%)" role="dialog" aria-modal="true" aria-label="{{ $selectedDocument['title'] }}">
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-white sm:px-6">
                <div class="min-w-0">
                    <h2 class="truncate text-base font-bold">{{ $selectedDocument['title'] }}</h2>
                    <p class="mt-0.5 hidden text-xs text-violet-200 sm:block" x-text="view === 'flip' ? 'Drag a page corner, use the arrows, or press ← → to turn pages. + / − zooms. Esc closes.' : 'Use the PDF toolbar to change pages or zoom. Esc closes.'"></p>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-sm font-semibold">
                    <div class="inline-flex rounded-lg border border-white/25 p-0.5" role="group" aria-label="Reader view">
                        <button type="button" class="min-h-9 rounded-md px-3" :class="view === 'flip' ? 'bg-white text-violet-800' : 'text-white'" :aria-pressed="(view === 'flip').toString()" @click="view = 'flip'">Flip book</button>
                        <button type="button" class="min-h-9 rounded-md px-3" :class="view === 'pdf' ? 'bg-white text-violet-800' : 'text-white'" :aria-pressed="(view === 'pdf').toString()" @click="view = 'pdf'">Plain PDF</button>
                    </div>
                    <a href="{{ route('admin.guides.show',$selectedGuide) }}" target="_blank" rel="noopener" class="vx-guide-open inline-flex min-h-10 items-center rounded-lg px-3 hover:bg-white/10">Open in browser</a>
                    <a href="{{ route('admin.guides.show',['guide'=>$selectedGuide,'download'=>1]) }}" class="inline-flex min-h-10 items-center gap-1.5 rounded-lg px-3 hover:bg-white/10"><x-heroicon-o-arrow-down-tray class="h-4 w-4"/>Download</a>
                    <button type="button" wire:click="closeReader" class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg bg-white/10 hover:bg-white/20" aria-label="Close guide"><x-heroicon-o-x-mark class="h-5 w-5"/></button>
                </div>
            </div>
            <div x-show="view === 'flip'" wire:ignore class="flex min-h-0 flex-1 flex-col">
                <div data-vx-flipbook data-src="{{ route('admin.guides.show',$selectedGuide) }}" data-title="{{ $selectedDocument['title'] }}" class="vx-flipbook flex-1" tabindex="0" aria-label="{{ $selectedDocument['title'] }} flip book">
                    <p class="vx-flipbook-status" data-status role="status">Opening guide…</p>
                    {{-- Pages scroll here; the controls below stay put instead of covering them. --}}
                    <div class="vx-flipbook-scroll" data-scroll><div class="vx-flipbook-stage"><div data-book></div></div></div>
                    <div class="vx-flipbook-controls" data-controls hidden>
                        <button type="button" data-prev aria-label="Previous page">‹ Prev</button>
                        <span data-page aria-live="polite"></span>
                        <button type="button" data-next aria-label="Next page">Next ›</button>
                        <span class="vx-flipbook-zoom" role="group" aria-label="Zoom">
                            <button type="button" data-zoom-out aria-label="Zoom out" title="Zoom out (−)">−</button>
                            <span data-zoom aria-live="polite">Fit width</span>
                            <button type="button" data-zoom-in aria-label="Zoom in" title="Zoom in (+)">+</button>
                        </span>
                        <button type="button" data-fullscreen>Full screen</button>
                    </div>
                </div>
            </div>
            <template x-if="view === 'pdf'">
                <iframe src="{{ route('admin.guides.show',$selectedGuide) }}" title="{{ $selectedDocument['title'] }} PDF" class="min-h-0 w-full flex-1 border-0 bg-white"></iframe>
            </template>
        </div>
        @endif
        <section x-data="{ ios: /iphone|ipad|ipod/i.test(navigator.userAgent), installed: window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true, canPrompt: !!document.documentElement.dataset.vxInstallable }"
                 x-on:beforeinstallprompt.window="canPrompt = true"
                 class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900" aria-labelledby="install-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <h2 id="install-heading" class="font-bold">Use VortexOps as an app</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        <span x-show="installed">You're using the installed app. It updates itself — there's nothing to reinstall.</span>
                        <span x-show="!installed && ios">On iPhone: tap Share, then <strong>Add to Home Screen</strong>. It opens full screen and updates itself.</span>
                        <span x-show="!installed && !ios">Install it on this device to open it full screen from your home screen or taskbar. It updates itself — there's nothing to reinstall.</span>
                    </p>
                </div>
                <x-filament::button x-show="!installed && canPrompt" x-cloak icon="heroicon-o-arrow-down-tray" x-on:click="window.vxInstallApp()">Install app</x-filament::button>
            </div>
        </section>
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
