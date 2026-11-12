@php
    $rows    = $this->rows;
    $overall = $this->overall;
@endphp

<x-filament-panels::page>
<x-reports.shell>
    <div class="vxr" data-vx-page="show-formats">

        <div class="vxr-toolbar vxr-toolbar--card">
            <label class="vxr-control"><x-filament::icon icon="heroicon-o-calendar" />
                <select wire:model.live="range" aria-label="Period">@foreach ($this->rangeOptions() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
            </label>
            <label class="vxr-control"><x-filament::icon icon="heroicon-o-signal" />
                <select wire:model.live="channelId" aria-label="Channel">@foreach ($this->channelOptions() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
            </label>
        </div>

        @if ($overall['shows'] > 0)
            @php
                $best = collect($rows)->filter(fn ($r) => $r['format'] !== null && $r['shows'] >= 3)->sortByDesc('avg_net')->first();
                $maxAvg = max(1, collect($rows)->max('avg_net'));
                $fmtColors = ['var(--vxc-1)', 'var(--vxc-2)', 'var(--vxc-3)', 'var(--vxc-4)', 'var(--vxw-accent)', 'var(--vxw-muted)'];
            @endphp
            <div class="vxr-stats" style="grid-template-columns:repeat(4,minmax(0,1fr))">
                <x-vxr.stat label="Shows in range" :value="number_format($overall['shows'])" icon="heroicon-o-video-camera" :note="number_format($overall['classified']).' classified'" />
                <x-vxr.stat label="Average per show" :value="'$'.number_format($overall['avg_net'], 0)" icon="heroicon-o-scale" note="The line each format is measured against" featured />
                <x-vxr.stat label="Total in range" :value="'$'.number_format($overall['total_net'], 0)" icon="heroicon-o-banknotes" note="Net where synced, gross where not" />
                <x-vxr.stat label="Best format" :value="$best['label'] ?? '—'" icon="heroicon-o-trophy" tone="ok" :note="$best ? '$'.number_format($best['avg_net'], 0).' a show · '.($best['net_vs_overall_pct'] >= 0 ? '+' : '').number_format($best['net_vs_overall_pct'] ?? 0, 0).'% vs average' : 'Needs 3+ shows of a format'" />
            </div>

            <div class="vxr-dash">
                <section class="vxr-card span-8">
                    <div class="vxr-card-head"><div><h2 class="vxr-card-title"><x-filament::icon icon="heroicon-o-chart-bar" /> Average Whatnot net per show</h2><p class="vxr-card-sub">Each format's typical show. The dashed line is the average of every show in range.</p></div>
                        @if (\App\Filament\Pages\ReportCompare::canAccess() && collect($rows)->whereNotNull('format')->count() >= 2)<a class="vxr-btn vxr-btn--sm vxr-btn--outline" href="{{ \App\Filament\Pages\ReportCompare::getUrl() }}?kind=format&items={{ collect($rows)->whereNotNull('format')->take(2)->pluck('format')->implode(',') }}">Compare</a>@endif
                    </div>
                    <div class="vxr-card-body vxr-lead">
                        @foreach ($rows as $i => $row)
                            <div class="vxr-lead-row" style="grid-template-columns:minmax(0,170px) minmax(0,1fr) auto">
                                <span class="nm">{{ $row['label'] }}<small>{{ number_format($row['shows']) }} {{ Str::plural('show', $row['shows']) }}@if($row['shows'] < 3) · too few to trust @endif</small></span>
                                <span style="position:relative;height:14px;border-radius:99px;background:var(--vxw-sunken)">
                                    <i style="position:absolute;inset:0 auto 0 0;border-radius:99px;width:{{ max(1, $row['avg_net'] / $maxAvg * 100) }}%;background:{{ $row['format'] === null ? 'var(--vxw-muted)' : 'linear-gradient(90deg,var(--vxr-hero-c),var(--vxr-hero-a))' }};animation:vxpGrow .8s cubic-bezier(.2,.7,.2,1) both"></i>
                                    <em style="position:absolute;top:-4px;bottom:-4px;left:{{ $overall['avg_net'] / $maxAvg * 100 }}%;border-left:2px dashed var(--vxw-text);opacity:.45"></em>
                                </span>
                                <span class="v">${{ number_format($row['avg_net'], 0) }} @if ($row['net_vs_overall_pct'] !== null)<span @class(['vxr-delta', 'is-up' => $row['net_vs_overall_pct'] > 0, 'is-down' => $row['net_vs_overall_pct'] < 0])>{{ $row['net_vs_overall_pct'] >= 0 ? '+' : '' }}{{ number_format($row['net_vs_overall_pct'], 0) }}%</span>@endif</span>
                            </div>
                        @endforeach
                    </div>
                </section>
                <section class="vxr-card span-4">
                    <div class="vxr-card-head"><div><h2 class="vxr-card-title"><x-filament::icon icon="heroicon-o-chart-pie" /> Mix of shows</h2><p class="vxr-card-sub">How many shows of each format ran.</p></div></div>
                    <div class="vxr-card-body">
                        <x-vxr.donut :money="false" center-label="shows" :segments="collect($rows)->values()->map(fn ($r, $i) => ['label' => $r['label'], 'value' => $r['shows'], 'color' => $r['format'] === null ? 'var(--vxw-border-strong, #c4c0d0)' : $fmtColors[min($i, 5)]])->all()" />
                    </div>
                </section>
            </div>
        @endif

        @if (empty($rows))
            <section class="rounded-xl border border-gray-200 bg-white p-5 text-center dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
                <x-heroicon-o-scale class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <h2 class="mt-3 text-base font-semibold text-gray-950 dark:text-white">No shows in this period</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Widen the period, or pick a different channel.</p>
            </section>
        @else
            @if ($this->unclassifiedCount > 0)
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200 sm:text-sm">
                    {{ number_format($this->unclassifiedCount) }} {{ Str::plural('show', $this->unclassifiedCount) }}
                    in this period {{ $this->unclassifiedCount === 1 ? 'has' : 'have' }} no format set. They are listed
                    below as their own group rather than folded into a default — an unclassified show is not a standard one.
                    Set a format from the Notes section of a show.
                </div>
            @endif

            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
                <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-5">
                    <h2 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">By format</h2>
                    <p class="mt-0.5 text-[11px] leading-4 text-gray-500 dark:text-gray-400 sm:text-xs">Best average first. The percentage compares each format's average show against the average of every show in range.</p>
                </div>

                {{-- Eight columns never fit a phone. Each format is its own block instead —
                     a header line, then the same numbers as a wrapping grid of small
                     stat tiles rather than table cells that force horizontal scroll. --}}
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($rows as $row)
                        <div class="p-4 {{ $row['format'] === null ? 'bg-gray-50/60 dark:bg-gray-800/30' : '' }}">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                <span class="font-medium text-gray-950 dark:text-white">
                                    {{ $row['label'] }}
                                    {{-- A single show is not a trend, and a table of averages
                                         invites reading one as though it were. --}}
                                    @if ($row['shows'] < 3)
                                        <span class="ml-1 rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-500 dark:bg-gray-800 dark:text-gray-400">too few to trust</span>
                                    @endif
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($row['shows']) }} shows</span>
                            </div>

                            <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-6">
                                <div>
                                    <div class="text-[10px] uppercase tracking-wide text-gray-500">Avg / show</div>
                                    <div class="font-semibold tabular-nums text-gray-950 dark:text-white">${{ number_format($row['avg_net'], 2) }}</div>
                                </div>
                                <div>
                                    <div class="text-[10px] uppercase tracking-wide text-gray-500">vs average</div>
                                    @if ($row['net_vs_overall_pct'] === null)
                                        <div class="tabular-nums text-gray-500">—</div>
                                    @else
                                        <div class="font-semibold tabular-nums {{ $row['net_vs_overall_pct'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                            {{ $row['net_vs_overall_pct'] >= 0 ? '+' : '' }}{{ number_format($row['net_vs_overall_pct'], 1) }}%
                                        </div>
                                    @endif
                                </div>
                                <div>
                                    <div class="text-[10px] uppercase tracking-wide text-gray-500">Avg units</div>
                                    <div class="tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($row['avg_units'], 1) }}</div>
                                </div>
                                <div>
                                    <div class="text-[10px] uppercase tracking-wide text-gray-500">Avg giveaways</div>
                                    <div class="tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($row['avg_giveaways'], 1) }}</div>
                                </div>
                                <div>
                                    <div class="text-[10px] uppercase tracking-wide text-gray-500">Avg buyers</div>
                                    <div class="tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($row['avg_buyers'], 1) }}</div>
                                </div>
                                <div>
                                    <div class="text-[10px] uppercase tracking-wide text-gray-500">Total</div>
                                    <div class="tabular-nums text-gray-700 dark:text-gray-300">${{ number_format($row['total_net'], 2) }}</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @php
            $tags = $this->titleTags;
        @endphp
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl" aria-labelledby="title-tags-heading">
            <div class="border-b border-gray-100 p-4 dark:border-gray-800 sm:p-5">
                <h2 id="title-tags-heading" class="text-base font-semibold text-gray-950 dark:text-white">By what the title offered</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 sm:text-sm">
                    Earnings per hour of streaming, by what each show's title promised. Read from titles automatically every night.
                    A show with several tags counts under each, so rows overlap.
                    @if ($tags['overall']['gross_per_hour'] !== null)
                        All shows in range: <strong>${{ number_format($tags['overall']['gross_per_hour'], 2) }}/hr gross</strong>, ${{ number_format($tags['overall']['net_per_hour'], 2) }}/hr net.
                    @endif
                </p>
                @if ($tags['untagged'] > 0)
                    <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">{{ number_format($tags['untagged']) }} {{ Str::plural('show', $tags['untagged']) }} not tagged yet — choose Tag titles now, or wait for tonight's run.</p>
                @endif
            </div>
            @if (empty($tags['rows']))
                <p class="p-6 text-center text-sm text-gray-500">No tagged shows in this period.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <tr><th class="px-4 py-2 sm:px-5">Title offered</th><th class="px-3 py-2 text-right">Shows</th><th class="px-3 py-2 text-right">Hours</th><th class="px-3 py-2 text-right">Gross / hr</th><th class="px-3 py-2 text-right">Net / hr</th><th class="px-4 py-2 text-right sm:px-5">vs all shows</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($tags['rows'] as $row)
                                <tr>
                                    <td class="px-4 py-2.5 font-medium text-gray-950 dark:text-white sm:px-5">{{ $row['label'] }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($row['shows']) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($row['hours'], 1) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['gross_per_hour'] !== null ? '$'.number_format($row['gross_per_hour'], 2) : '—' }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['net_per_hour'] !== null ? '$'.number_format($row['net_per_hour'], 2) : '—' }}</td>
                                    <td class="px-4 py-2.5 text-right tabular-nums sm:px-5 {{ ($row['gross_vs_overall_pct'] ?? 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                        {{ $row['gross_vs_overall_pct'] === null ? '—' : (($row['gross_vs_overall_pct'] >= 0 ? '+' : '').number_format($row['gross_vs_overall_pct'], 1).'%') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-reports.shell>
</x-filament-panels::page>
