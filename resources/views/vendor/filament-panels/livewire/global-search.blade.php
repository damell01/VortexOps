{{--
    Overrides filament-panels::livewire.global-search.

    Only the results list differs from the packaged view: each group gains a
    "view all" link into that resource filtered by the same term, and a result
    renders as an icon tile + title + muted subtitle + status pill + a
    right-aligned figure.

    Resources opt into that shape from getGlobalSearchResultDetails() using
    lowercase keys, which are read as slots rather than printed as labels:

        subtitle  muted second line — whatever identifies the record next
                  (a SKU, an email, a show title)
        status    pill text
        tone      pill colour: success | warning | danger | info | neutral.
                  A tone rather than a status slug, so a resource picks the
                  meaning instead of every status string needing its own rule.
        figure    the one number worth pinning right

    Any other key still renders as the packaged label/value pair, so a resource
    that opts out is unaffected.

    Re-check this against the packaged view after a Filament upgrade.
--}}
@php
    $debounce = filament()->getGlobalSearchDebounce();

    // Group keys are resource plural labels, so map those back to a URL and an
    // icon for the group header and the result tiles.
    $vxGroups = [];

    foreach (filament()->getResources() as $vxResource) {
        if (! $vxResource::canAccess()) {
            continue;
        }

        try {
            $vxGroups[(string) $vxResource::getPluralModelLabel()] = [
                'url'  => $vxResource::getUrl('index'),
                'icon' => $vxResource::getNavigationIcon(),
            ];
        } catch (\Throwable) {
            // A resource without an index route just gets no "view all" link.
        }
    }
    $keyBindings = filament()->getGlobalSearchKeyBindings();
    $suffix = filament()->getGlobalSearchFieldSuffix();
@endphp

<div class="fi-global-search-ctn">
    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_START) }}

    <div
        x-on:focus-first-global-search-result.stop="$el.querySelector('.fi-global-search-result-link')?.focus()"
        class="fi-global-search"
    >
        <div x-id="['input']" class="fi-global-search-field">
            <label x-bind:for="$id('input')" class="fi-sr-only">
                {{ __('filament-panels::global-search.field.label') }}
            </label>

            <x-filament::input.wrapper
                :prefix-icon="\Filament\Support\Icons\Heroicon::MagnifyingGlass"
                :prefix-icon-alias="\Filament\View\PanelsIconAlias::GLOBAL_SEARCH_FIELD"
                inline-prefix
                :suffix="$suffix"
                inline-suffix
                wire:target="search"
            >
                <input
                    autocomplete="off"
                    maxlength="1000"
                    placeholder="{{ __('filament-panels::global-search.field.placeholder') }}"
                    type="search"
                    wire:key="global-search.field.input"
                    x-bind:id="$id('input')"
                    x-on:keydown.down.prevent.stop="$dispatch('focus-first-global-search-result')"
                    wire:model.live.debounce.{{ $debounce }}="search"
                    x-mousetrap.global.{{ collect($keyBindings)->map(fn (string $keyBinding): string => str_replace('+', '-', $keyBinding))->implode('.') }}="document.getElementById($id('input'))?.focus()"
                    class="fi-input fi-input-has-inline-prefix"
                />
            </x-filament::input.wrapper>
        </div>

        @if ($results !== null)
            <div
                x-data="{
                    isOpen: false,

                    open(event) {
                        this.isOpen = true
                    },

                    close(event) {
                        this.isOpen = false
                    },
                }"
                x-init="$nextTick(() => open())"
                x-on:click.away="close()"
                x-on:keydown.escape.window="close()"
                x-on:keydown.up.prevent="$focus.wrap().previous()"
                x-on:keydown.down.prevent="$focus.wrap().next()"
                x-on:open-global-search-results.window="$nextTick(() => open())"
                x-show="isOpen"
                x-transition:enter-start="fi-transition-enter-start"
                x-transition:leave-end="fi-transition-leave-end"
                class="fi-global-search-results-ctn"
            >
                @if ($results->getCategories()->isEmpty())
                    <p class="fi-global-search-no-results-message">
                        {{ __('filament-panels::global-search.no_results_message') }}
                    </p>
                @else
                    <ul class="fi-global-search-results">
                        @foreach ($results->getCategories() as $group => $groupedResults)
                            <li class="fi-global-search-result-group">
                                <h3
                                    class="fi-global-search-result-group-header vx-gs-group-header"
                                >
                                    <span>{{ $group }}</span>

                                    @if ($vxUrl = ($vxGroups[$group]['url'] ?? null))
                                        <a href="{{ $vxUrl }}?tableSearch={{ urlencode($search) }}"
                                           class="vx-gs-viewall">
                                            View all {{ $group }}
                                            <span aria-hidden="true">&rarr;</span>
                                        </a>
                                    @endif
                                </h3>

                                <ul
                                    class="fi-global-search-result-group-results"
                                >
                                    @foreach ($groupedResults as $result)
                                        @php
                                            $resultVisibleActions = $result->getVisibleActions();
                                        @endphp

                                        <li
                                            @class([
                                                'fi-global-search-result',
                                                'fi-global-search-result-has-actions' => $resultVisibleActions,
                                            ])
                                        >
                                            <a
                                                {{ \Filament\Support\generate_href_html($result->url) }}
                                                x-on:click="close()"
                                                class="fi-global-search-result-link"
                                            >
                                                @php
                                                    $vxIcon     = $vxGroups[$group]['icon'] ?? null;
                                                    $vxSubtitle = $result->details['subtitle'] ?? null;
                                                    $vxStatus   = $result->details['status'] ?? null;
                                                    $vxTone     = $result->details['tone'] ?? 'neutral';
                                                    $vxFigure   = $result->details['figure'] ?? null;
                                                    $vxRest     = collect($result->details ?? [])
                                                        ->except(['subtitle', 'status', 'tone', 'figure'])
                                                        ->all();
                                                @endphp

                                                @if ($vxIcon)
                                                    <span class="vx-gs-tile">
                                                        <x-filament::icon :icon="$vxIcon" class="vx-gs-tile-glyph" />
                                                    </span>
                                                @endif

                                                <span class="vx-gs-main">
                                                    <span class="vx-gs-line">
                                                        <h4 class="fi-global-search-result-heading">
                                                            {{ $result->title }}
                                                        </h4>

                                                        @if ($vxStatus)
                                                            <span class="vx-gs-pill is-{{ \Illuminate\Support\Str::slug($vxTone) }}">
                                                                {{ $vxStatus }}
                                                            </span>
                                                        @endif
                                                    </span>

                                                    @if ($vxSubtitle)
                                                        <span class="vx-gs-subtitle">{{ $vxSubtitle }}</span>
                                                    @endif
                                                </span>

                                                @if ($vxFigure)
                                                    <span class="vx-gs-figure">{{ $vxFigure }}</span>
                                                @endif

                                                @if ($vxRest)
                                                    <dl
                                                        class="fi-global-search-result-details"
                                                    >
                                                        @foreach ($vxRest as $label => $value)
                                                            <div
                                                                class="fi-global-search-result-detail"
                                                            >
                                                                @if (\Illuminate\Support\Arr::isAssoc($vxRest))
                                                                    <dt
                                                                        class="fi-global-search-result-detail-label"
                                                                    >
                                                                        {{ $label }}:
                                                                    </dt>
                                                                @endif

                                                                <dd
                                                                    class="fi-global-search-result-detail-value"
                                                                >
                                                                    {{ $value }}
                                                                </dd>
                                                            </div>
                                                        @endforeach
                                                    </dl>
                                                @endif
                                            </a>

                                            @if ($resultVisibleActions)
                                                <div
                                                    class="fi-global-search-result-actions"
                                                >
                                                    @foreach ($resultVisibleActions as $action)
                                                        {{ $action }}
                                                    @endforeach
                                                </div>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </div>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_END) }}
</div>
