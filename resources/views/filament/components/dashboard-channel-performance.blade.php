{{-- Current month only: one ranked bar per channel, with the value printed on each row. --}}
<section class="min-w-0 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
    x-data="{ metric:'gross', series:@js($dashboardOverview['series']),
        value(s){return s.values[this.metric][0] || 0},
        sorted(){return [...this.series].sort((a,b)=>this.value(b)-this.value(a))},
        total(){return this.series.reduce((sum,s)=>sum+this.value(s),0)},
        max(){return Math.max(1,...this.series.map(s=>Math.abs(this.value(s))))},
        money(v){return new Intl.NumberFormat('en-US',{style:'currency',currency:'USD',maximumFractionDigits:Math.abs(v)>=1000?0:2}).format(v)}
    }">
    <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-base font-bold text-gray-950 dark:text-white">Channel Performance</h2><div class="flex flex-wrap gap-1 rounded-xl bg-gray-50 p-1 dark:bg-gray-800" role="group" aria-label="Whatnot revenue">
        @foreach(['gross'=>'Gross revenue','net'=>'Net revenue'] as $key=>$label)
        <button type="button" @click="metric=@js($key)" :aria-pressed="metric===@js($key)" :class="metric===@js($key) ? 'bg-white text-violet-700 shadow-sm dark:bg-gray-700 dark:text-violet-300' : 'text-gray-500'" class="min-h-9 rounded-lg px-2 py-1 text-[10px] font-semibold">{{ $label }}</button>
        @endforeach
    </div></div>
    <p class="mt-2 text-xs text-gray-500">{{ $dashboardOverview['period'] }} · Whatnot <span x-text="metric === 'gross' ? 'gross sales before fees' : 'net earnings after fees (settled, or Whatnot\'s estimate until a show settles)'">gross sales before fees</span>.</p>
    @if(count($dashboardOverview['series']))
    <div class="mt-4 flex items-baseline justify-between gap-3 border-b border-gray-100 pb-3 dark:border-gray-800">
        <span class="text-sm text-gray-500">All channels</span>
        <span class="text-2xl font-bold tabular-nums text-gray-950 dark:text-white" x-text="money(total())"></span>
    </div>
    <ul class="mt-3 space-y-3" aria-label="Revenue by channel this month">
        <template x-for="s in sorted()" :key="s.name">
            <li>
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="inline-flex min-w-0 items-center gap-2"><span class="h-2.5 w-2.5 shrink-0 rounded" :style="`background:${s.color}`"></span><span class="truncate font-medium text-gray-800 dark:text-gray-100" x-text="s.name"></span></span>
                    <span class="font-semibold tabular-nums text-gray-950 dark:text-white" x-text="money(value(s))"></span>
                </div>
                <div class="mt-1.5 h-2.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800" aria-hidden="true">
                    <div class="h-full rounded-full transition-all duration-300" :style="`width:${Math.max(0, value(s)) / max() * 100}%;background:${s.color}`"></div>
                </div>
            </li>
        </template>
    </ul>
    @else<p class="py-12 text-center text-sm text-gray-500">No recorded channel performance yet this month.</p>@endif
</section>
