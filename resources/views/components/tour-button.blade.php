<div
    id="vortexops-tour-btn"
    x-data
    x-cloak
    style="position:fixed;top:0.875rem;right:5.75rem;z-index:45;"
>
    <button
        @click="window.vortexTour?.start()"
        title="Help & guided tour"
        style="
            display:flex;align-items:center;gap:0.4rem;
            background:rgba(255,255,255,0.92);border:1px solid #e5e7eb;border-radius:9999px;
            padding:0.42rem 0.8rem;font-size:0.78rem;font-weight:600;
            color:#374151;cursor:pointer;box-shadow:0 1px 6px rgba(0,0,0,0.10);
            backdrop-filter:blur(10px);
            transition:box-shadow 0.15s,border-color 0.15s,transform 0.15s;
        "
        onmouseover="this.style.boxShadow='0 4px 14px rgba(0,0,0,0.14)';this.style.borderColor='#c4b5fd';this.style.transform='translateY(-1px)';"
        onmouseout="this.style.boxShadow='0 1px 6px rgba(0,0,0,0.10)';this.style.borderColor='#e5e7eb';this.style.transform='translateY(0)';"
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
