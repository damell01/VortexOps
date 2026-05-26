<div
    id="vortexops-tour-btn"
    x-data
    x-cloak
    style="position:fixed;bottom:1.25rem;left:1.25rem;z-index:50;"
>
    <button
        @click="window.vortexTour?.start()"
        title="Help & guided tour"
        style="
            display:flex;align-items:center;gap:0.4rem;
            background:#fff;border:1.5px solid #e5e7eb;border-radius:9999px;
            padding:0.45rem 0.85rem;font-size:0.8rem;font-weight:600;
            color:#374151;cursor:pointer;box-shadow:0 1px 6px rgba(0,0,0,0.10);
            transition:box-shadow 0.15s,border-color 0.15s;
        "
        onmouseover="this.style.boxShadow='0 2px 10px rgba(0,0,0,0.15)';this.style.borderColor='#6b7280';"
        onmouseout="this.style.boxShadow='0 1px 6px rgba(0,0,0,0.10)';this.style.borderColor='#e5e7eb';"
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
</div>
