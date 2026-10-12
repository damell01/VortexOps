<section class="min-w-0 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
    x-data="{ metric:'gross', series:@js($dashboardOverview['series']), months:@js($dashboardOverview['months']),
        extent(){let values=this.series.flatMap(s=>s.values[this.metric]);return {low:Math.min(0,...values),high:Math.max(1,...values)}},
        y(value){let e=this.extent();return 190-(value-e.low)/(e.high-e.low)*170},
        barWidth(){return Math.max(3,Math.min(14,550/this.months.length/Math.max(1,this.series.length)-3))},
        barX(month,channel){let group=550/this.months.length,w=this.barWidth();return 45+month*group+(group-this.series.length*(w+3))/2+channel*(w+3)},
        format(value){return new Intl.NumberFormat('en-US',{notation:'compact',maximumFractionDigits:1,style:'currency',currency:'USD'}).format(value)}
    }">
    <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-base font-bold text-gray-950 dark:text-white">Channel Performance</h2><div class="flex flex-wrap gap-1 rounded-xl bg-gray-50 p-1 dark:bg-gray-800" role="group" aria-label="Whatnot revenue">
        @foreach(['gross'=>'Gross revenue','net'=>'Net revenue'] as $key=>$label)
        <button type="button" @click="metric=@js($key)" :aria-pressed="metric===@js($key)" :class="metric===@js($key) ? 'bg-white text-violet-700 shadow-sm dark:bg-gray-700 dark:text-violet-300' : 'text-gray-500'" class="min-h-9 rounded-lg px-2 py-1 text-[10px] font-semibold">{{ $label }}</button>
        @endforeach
    </div></div>
    <p class="mt-2 text-xs text-gray-500">{{ $dashboardOverview['period'] }} · Whatnot <span x-text="metric === 'gross' ? 'gross sales before fees' : 'net earnings after fees (settled, or Whatnot\'s estimate until a show settles)'">gross sales before fees</span>.</p>
    @if(count($dashboardOverview['series']))
    <svg viewBox="0 0 640 225" role="img" aria-label="Monthly channel performance. Use View figures for the data table." class="mt-4 w-full overflow-visible">
        @for($i=0;$i<=4;$i++)
        <line x1="45" x2="605" y1="{{ 20+$i*42.5 }}" y2="{{ 20+$i*42.5 }}" stroke="currentColor" class="text-gray-100 dark:text-gray-800" />
        <text x="39" y="{{ 24+$i*42.5 }}" text-anchor="end" fill="currentColor" class="text-gray-400" font-size="9" x-text="format(extent().high-{{ $i }}/4*(extent().high-extent().low))"></text>
        @endfor
        @foreach($dashboardOverview['months'] as $mi=>$month)
        <text x="{{ 45+($mi+.5)*550/count($dashboardOverview['months']) }}" y="211" text-anchor="middle" fill="currentColor" class="text-gray-400" font-size="10">{{ $month['label'] }}</text>
        @foreach($dashboardOverview['series'] as $ci=>$channel)
        <rect :x="barX({{ $mi }},{{ $ci }})" :y="Math.min(y(series[{{ $ci }}].values[metric][{{ $mi }}]),y(0))" :width="barWidth()" :height="Math.abs(y(series[{{ $ci }}].values[metric][{{ $mi }}])-y(0))" fill="{{ $channel['color'] }}" rx="2"><title x-text="series[{{ $ci }}].name+': '+format(series[{{ $ci }}].values[metric][{{ $mi }}])"></title></rect>
        @endforeach
        @endforeach
    </svg>
    <div class="flex flex-wrap justify-center gap-x-4 gap-y-2 text-[10px] text-gray-600 dark:text-gray-300">@foreach($dashboardOverview['series'] as $channel)<span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded" style="background:{{ $channel['color'] }}"></span>{{ $channel['name'] }}</span>@endforeach</div>
    <details class="mt-4 text-xs text-gray-500"><summary class="cursor-pointer font-medium">View figures</summary><div class="mt-3 overflow-x-auto"><table class="w-full text-left"><caption class="sr-only">Monthly recorded channel metrics</caption><thead><tr><th class="p-2">Month</th>@foreach($dashboardOverview['series'] as $channel)<th class="p-2">{{ $channel['name'] }}</th>@endforeach</tr></thead><tbody>@foreach($dashboardOverview['months'] as $mi=>$month)<tr><th class="p-2 font-medium">{{ $month['label'] }}</th>@foreach($dashboardOverview['series'] as $ci=>$channel)<td class="p-2 tabular-nums" x-text="new Intl.NumberFormat('en-US',{maximumFractionDigits:2}).format(series[{{ $ci }}].values[metric][{{ $mi }}])"></td>@endforeach</tr>@endforeach</tbody></table></div></details>
    @else<p class="py-12 text-center text-sm text-gray-500">No recorded channel performance in this period.</p>@endif
</section>
