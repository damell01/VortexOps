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
<style>
/* Global search results: explicit text colors in both themes, including hover.
   Scope to this palette so the purple header cannot tint result text. */
.fi-global-search-results-ctn {
  background:#fff!important;color:#1b1830!important;
  border:1px solid #e7e2f0!important;border-radius:16px!important;
}
.fi-global-search-results-ctn .fi-global-search-result-group-header {
  display:flex;align-items:center;justify-content:space-between;gap:12px;
  background:#f7f5fc!important;color:#312849!important;
  padding:11px 14px!important;font-weight:750!important;
}
.fi-global-search-results-ctn .vx-gs-viewall {color:#6227b7!important;font-size:12px;font-weight:650;text-decoration:none}
.fi-global-search-results-ctn .fi-global-search-result-link {
  display:flex;align-items:center;gap:12px;
  padding:12px 14px!important;color:#1b1830!important;
  background:#fff!important;text-decoration:none!important;
  border-bottom:1px solid #efedf4!important;
}
.fi-global-search-results-ctn .fi-global-search-result-link:is(:hover,:focus-visible) {
  background:#f1eafd!important;color:#1b1830!important;
  outline-offset:-2px;
}
.fi-global-search-results-ctn .vx-gs-main {display:flex;flex-direction:column;gap:4px;min-width:0;flex:1}
.fi-global-search-results-ctn .vx-gs-line {display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.fi-global-search-results-ctn .fi-global-search-result-heading {
  color:#19152d!important;font-size:13px!important;font-weight:750!important;
  line-height:1.35!important;overflow-wrap:anywhere;
}
.fi-global-search-results-ctn .vx-gs-subtitle,
.fi-global-search-results-ctn .fi-global-search-result-detail-value {
  color:#57526c!important;font-size:12px!important;line-height:1.45!important;
}
.fi-global-search-results-ctn .fi-global-search-result-detail-label {color:#635b76!important}
.fi-global-search-results-ctn .vx-gs-tile {
  display:flex;align-items:center;justify-content:center;flex:none;
  width:34px;height:34px;border-radius:10px;
  background:#f0e9ff!important;color:#6025bc!important;
}
.fi-global-search-results-ctn .vx-gs-tile svg {width:18px;height:18px;color:inherit!important}
.fi-global-search-results-ctn .vx-gs-figure {color:#39246a!important;font-size:12px;font-weight:700}
.fi-global-search-results-ctn .vx-gs-pill {
  display:inline-flex;align-items:center;border-radius:999px;
  padding:3px 8px;font-size:11px;font-weight:700;
  color:#40375c!important;background:#ede9f5!important;
}
.fi-global-search-results-ctn .vx-gs-pill.is-success {background:#dcfce7!important;color:#166534!important}
.fi-global-search-results-ctn .vx-gs-pill.is-warning {background:#fef3c7!important;color:#92400e!important}
.fi-global-search-results-ctn .vx-gs-pill.is-danger {background:#fee2e2!important;color:#991b1b!important}
.fi-global-search-results-ctn .vx-gs-pill.is-info {background:#dbeafe!important;color:#1d4ed8!important}
.fi-global-search-results-ctn .fi-global-search-no-results-message {color:#57526c!important}
.dark .fi-global-search-results-ctn {background:#211b30!important;color:#f6f2ff!important;border-color:#54446c!important}
.dark .fi-global-search-results-ctn .fi-global-search-result-group-header {background:#302541!important;color:#f4edff!important}
.dark .fi-global-search-results-ctn .vx-gs-viewall {color:#d1adff!important}
.dark .fi-global-search-results-ctn .fi-global-search-result-link {background:#211b30!important;color:#f6f2ff!important;border-color:#443653!important}
.dark .fi-global-search-results-ctn .fi-global-search-result-link:is(:hover,:focus-visible) {background:#3b2b53!important;color:#fff!important}
.dark .fi-global-search-results-ctn .fi-global-search-result-heading {color:#fff!important}
.dark .fi-global-search-results-ctn .vx-gs-subtitle,
.dark .fi-global-search-results-ctn .fi-global-search-result-detail-value {color:#c9bedb!important}
.dark .fi-global-search-results-ctn .fi-global-search-result-detail-label {color:#b9a9d0!important}
.dark .fi-global-search-results-ctn .vx-gs-tile {background:#46315e!important;color:#e1caff!important}
.dark .fi-global-search-results-ctn .vx-gs-figure {color:#e1caff!important}
@media(max-width:640px) {
  .fi-global-search-results-ctn .fi-global-search-result-link {padding:10px!important;gap:8px}
  .fi-global-search-results-ctn .vx-gs-line {gap:6px}
}
</style>

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
