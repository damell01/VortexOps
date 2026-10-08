<x-filament-panels::page>
    <div class="mx-auto w-full max-w-5xl space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-xl font-bold">A little help, right where you work</h2>
            <p class="mt-2 text-sm text-gray-500">Choose a walkthrough or open a guide. Walkthroughs explain the screen without changing records. You can close them at any time and replay them from Help.</p>
        </div>
        <section aria-labelledby="walkthroughs-heading">
            <h2 id="walkthroughs-heading" class="mb-3 text-lg font-bold">Interactive walkthroughs</h2>
            <div class="grid gap-4 md:grid-cols-2">
                @forelse(\App\Support\GuidedHelp::tours() as $id => $tour)
                    <article class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                        <h3 class="font-bold">{{ $tour['title'] }}</h3>
                        <p class="mt-2 text-sm text-gray-500">{{ $tour['description'] }}</p>
                        <a href="{{ $tour['url'] }}?vx-tour={{ $id }}" class="mt-4 inline-flex min-h-11 items-center rounded-xl bg-primary-600 px-4 text-sm font-semibold text-white">Start walkthrough</a>
                        <details class="mt-4 text-sm"><summary class="cursor-pointer font-semibold">Read the steps</summary><ol class="mt-3 list-decimal space-y-3 pl-5">@foreach($tour['steps'] as $step)<li><strong>{{ $step['title'] }}</strong><p class="mt-1 text-gray-500">{{ $step['body'] }}</p></li>@endforeach</ol></details>
                    </article>
                @empty
                    <p class="text-sm text-gray-500">No walkthroughs are available for your current screens. Use the reference guides below.</p>
                @endforelse
            </div>
        </section>
        <section aria-labelledby="pdf-guides-heading">
            <h2 id="pdf-guides-heading" class="mb-3 text-lg font-bold">PDF guides</h2>
            <div class="grid gap-4 md:grid-cols-2">
                @forelse(\App\Support\GuidedHelp::documents() as $id => $doc)
                    <article class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                        <h3 class="font-bold">{{ $doc['title'] }}</h3><p class="mt-2 text-sm text-gray-500">{{ $doc['description'] }}</p>
                        <div class="mt-4 flex flex-wrap gap-3"><a href="{{ route('admin.guides.show', $id) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-xl bg-primary-600 px-4 text-sm font-semibold text-white">View PDF <span class="sr-only">in a new tab</span></a><a href="{{ route('admin.guides.show', $id) }}?download=1" class="inline-flex min-h-11 items-center rounded-xl border border-gray-300 px-4 text-sm font-semibold dark:border-gray-600">Download</a></div>
                    </article>
                @empty
                    <p class="text-sm text-gray-500">A dedicated PDF for your role has not been added yet.</p>
                @endforelse
            </div>
        </section>
        <div class="rounded-2xl border border-gray-200 p-5 dark:border-gray-700">
            <h2 class="font-bold">More reference help</h2>
            <div class="mt-3 flex flex-wrap gap-4 text-sm font-semibold text-primary-600">
                @if(\App\Filament\Pages\HowItWorks::canAccess())<a href="{{ \App\Filament\Pages\HowItWorks::getUrl(panel:'admin') }}">Your role & workflow</a>@endif
                @if(\App\Filament\Pages\Handbook::canAccess() && ! \App\Support\NavVisibility::isHiddenForUser(\App\Filament\Pages\Handbook::class, auth()->user()))<a href="{{ \App\Filament\Pages\Handbook::getUrl(panel:'admin') }}?module={{ auth()->user()?->isFulfillment() ? 'fulfillment' : 'inventory' }}">Operations handbook</a>@endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
