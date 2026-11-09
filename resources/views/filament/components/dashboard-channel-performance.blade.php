{{-- Live monthly show revenue, never demo figures. All charts derive from dashboardOverview series. --}}
<section class="vx-channel-chart min-w-0 rounded-2xl border border-violet-200 bg-white p-4 shadow-sm dark:border-violet-900 dark:bg-gray-900 sm:p-5"
    x-data="{
        metric:'gross', detail:false, series:@js($dashboardOverview['series']),
        labels:@js($dashboardOverview['months'] ?? []),
        value(s,i){return Number(s.values?.[this.metric]?.[i] ?? 0)},
        length(){return Math.max(0,...this.series.map(s=>s.values?.[this.metric]?.length || 0))},
        total(i){return this.series.reduce((sum,s)=>sum+this.value(s,i),0)},
        grand(){return Array.from({length:this.length()},(_,i)=>this.total(i)).reduce((a,b)=>a+b,0)},
        max(){return Math.max(1,...Array.from({length:this.length()},(_,i)=>Math.max(0,this.total(i))))},
        height(v){return Math.max(0,Number(v))/this.max()*124},
        x(i){return 38+(i+.5)*550/Math.max(1,this.length())},
        y(v){return 160-this.height(v)},
        points(){return Array.from({length:this.length()},(_,i)=>this.x(i)+','+this.y(this.total(i))).join(' ')},
        money(v){return new Intl.NumberFormat('en-US',{style:'currency',currency:'USD',maximumFractionDigits:Math.abs(v)>=1000?0:2}).format(v)},
        compact(v){return new Intl.NumberFormat('en-US',{notation:'compact',maximumFractionDigits:1}).format(v)},
        month(i){const l=this.labels[i];return typeof l==='string'?l:(l?.label ?? l?.key ?? 'Month '+(i+1))},
        ranked(){return [...this.series].sort((a,b)=>b.values[this.metric].reduce((x,y)=>x+Number(y||0),0)-a.values[this.metric].reduce((x,y)=>x+Number(y||0),0))}
    }">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h2 class="text-base font-extrabold text-gray-950 dark:text-white">Channel Performance</h2><p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Monthly Whatnot revenue across channels</p></div>
        <div class="inline-flex rounded-xl border border-violet-200 bg-violet-50 p-1 dark:border-violet-800 dark:bg-gray-800" role="group" aria-label="Revenue metric">
            <button type="button" @click="metric='gross'" :aria-pressed="metric==='gross'" :class="metric==='gross'?'bg-violet-700 text-white shadow-sm':'text-gray-600 dark:text-gray-300'" class="min-h-9 rounded-lg px-3 text-xs font-bold">Gross</button>
            <button type="button" @click="metric='net'" :aria-pressed="metric==='net'" :class="metric==='net'?'bg-violet-700 text-white shadow-sm':'text-gray-600 dark:text-gray-300'" class="min-h-9 rounded-lg px-3 text-xs font-bold">Net</button>
        </div>
    </div>
    <div class="mt-3 flex flex-wrap items-baseline justify-between gap-2 border-b border-gray-100 pb-3 dark:border-gray-800">
        <div><span class="text-xs text-gray-500 dark:text-gray-400">{{ $dashboardOverview['period'] }} · </span><span class="text-xs text-gray-500 dark:text-gray-400" x-text="metric==='gross'?'Gross sales before fees':'Completed earnings or available net estimate'"></span></div>
        <strong class="text-xl font-extrabold tabular-nums text-gray-950 dark:text-white" x-text="money(grand())"></strong>
    </div>
    <template x-if="length()>0">
        <div>
            <svg viewBox="0 0 620 206" class="mt-3 w-full" role="img" aria-label="Monthly revenue by channel as stacked bars and total revenue as a line">
                <template x-for="tick in [0,.5,1]" :key="tick">
                    <g><line x1="38" :y1="160-tick*124" x2="588" :y2="160-tick*124" stroke="currentColor" opacity=".12"/><text x="30" :y="164-tick*124" text-anchor="end" fill="currentColor" font-size="10" x-text="compact(max()*tick)"></text></g>
                </template>
                <template x-for="(s,j) in series" :key="s.name">
                    <g><template x-for="i in length()" :key="i">
                        <rect :x="x(i)-Math.min(38,440/length())/2" :y="160-height(series.slice(0,j+1).reduce((sum,t)=>sum+Math.max(0,value(t,i)),0))" :width="Math.min(38,440/length())" :height="height(value(s,i))" :fill="s.color" opacity=".82"><title x-text="s.name+' · '+month(i)+': '+money(value(s,i))"></title></rect>
                    </template></g>
                </template>
                <polyline :points="points()" fill="none" stroke="#a855f7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                <template x-for="i in length()" :key="'point'+i">
                    <g><circle :cx="x(i)" :cy="y(total(i))" r="4" fill="#a855f7" stroke="white" stroke-width="1.5"><title x-text="month(i)+' total: '+money(total(i))"></title></circle>
                        <text :x="x(i)" y="180" text-anchor="middle" font-size="10" fill="currentColor" x-text="month(i)"></text></g>
                </template>
            </svg>
            <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-600 dark:text-gray-300" aria-label="Chart legend">
                <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-purple-500"></span>Total trend</span>
                <template x-for="s in series" :key="'legend'+s.name"><span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm" :style="'background:'+s.color"></span><span x-text="s.name"></span></span></template>
            </div>
            <button type="button" class="mt-4 inline-flex min-h-9 items-center gap-2 text-xs font-bold text-violet-700 hover:underline dark:text-violet-300" @click="detail=!detail" :aria-expanded="detail" x-text="detail?'Hide channel details −':'View channel details +'" ></button>
            <div x-show="detail" x-cloak class="mt-2 space-y-3 border-t border-gray-100 pt-3 dark:border-gray-800">
                <template x-for="s in ranked()" :key="'row'+s.name">
                    <div><div class="flex items-center justify-between gap-2 text-xs"><span class="inline-flex min-w-0 items-center gap-2 font-semibold"><span class="h-2.5 w-2.5 shrink-0 rounded" :style="'background:'+s.color"></span><span x-text="s.name"></span></span><strong class="tabular-nums" x-text="money(s.values[metric].reduce((a,b)=>a+Number(b||0),0))"></strong></div>
                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-full rounded-full" :style="'background:'+s.color+';width:'+(grand()?Math.max(0,s.values[metric].reduce((a,b)=>a+Number(b||0),0))/Math.max(1,grand())*100:0)+'%'"></div></div></div>
                </template>
            </div>
            <details class="mt-2 text-xs text-gray-600 dark:text-gray-300"><summary class="cursor-pointer font-semibold">Accessible monthly totals</summary>
                <div class="mt-2 overflow-x-auto"><table class="w-full text-left"><thead><tr><th class="p-2">Month</th><th class="p-2">Total</th></tr></thead><tbody><template x-for="i in length()" :key="'table'+i"><tr class="border-t border-gray-100 dark:border-gray-800"><td class="p-2" x-text="month(i)"></td><td class="p-2 tabular-nums" x-text="money(total(i))"></td></tr></template></tbody></table></div>
            </details>
        </div>
    </template>
    <template x-if="length()===0"><p class="py-8 text-center text-sm text-gray-500">No recorded channel performance for this period.</p></template>
</section>
