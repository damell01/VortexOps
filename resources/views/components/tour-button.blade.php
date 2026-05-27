@props([
    'showTour' => true,
])

<div id="vortexops-top-actions" class="flex items-center gap-2">
    @if ($showTour)
    <button
        id="vortexops-tour-btn"
        onclick="window.vortexTour?.start()"
        title="Help & guided tour"
        class="inline-flex items-center gap-1.5 rounded-full border border-[rgba(41,231,231,0.22)] bg-white/90 px-3 py-1.5 text-[0.78rem] font-semibold text-[#1f2a44] shadow-sm backdrop-blur-sm transition hover:-translate-y-px hover:border-cyan-300 hover:shadow-md"
    >
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
            <path d="M12 17h.01"/>
        </svg>
        Tour
    </button>
    @endif
</div>
