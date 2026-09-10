<x-filament-panels::page>
    <style>
        .vx-received-history .fi-ta-ctn{border-radius:1rem!important;overflow:hidden!important;border:1px solid rgb(226 232 240)!important;box-shadow:0 1px 3px rgba(15,23,42,.05)!important}
        .dark .vx-received-history .fi-ta-ctn{border-color:rgb(51 65 85)!important}
        .vx-received-history .fi-ta-header{padding:1rem!important}
        .vx-received-history .fi-ta-header-cell{background:rgb(248 250 252)!important}
        .dark .vx-received-history .fi-ta-header-cell{background:rgb(30 41 59)!important}
        .vx-received-history .fi-ta-row{min-height:68px}
        .vx-received-history .fi-ta-cell{padding-top:.85rem!important;padding-bottom:.85rem!important}
        .vx-history-intro{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem;padding:1rem 1.1rem;border:1px solid rgb(221 214 254);border-radius:1rem;background:linear-gradient(135deg,rgb(250 245 255),rgb(238 242 255))}
        .dark .vx-history-intro{border-color:rgb(76 29 149);background:linear-gradient(135deg,rgba(76,29,149,.25),rgba(49,46,129,.2))}
        .vx-history-intro strong{display:block;font-size:.95rem;color:rgb(76 29 149)}.dark .vx-history-intro strong{color:rgb(221 214 254)}
        .vx-history-intro span{display:block;margin-top:.2rem;font-size:.78rem;color:rgb(100 116 139)}.dark .vx-history-intro span{color:rgb(203 213 225)}
        @media(max-width:640px){.vx-history-intro{display:block}.vx-received-history .fi-ta-ctn{border-radius:.8rem!important}}
    </style>
    <div class="vx-received-history">
        <div class="vx-history-intro">
            <div>
                <strong>Completed receiving archive</strong>
                <span>Received and processed pallets live here so the main Receive Inventory screen stays focused on work still in progress.</span>
            </div>
        </div>
        {{ $this->table }}
    </div>
</x-filament-panels::page>
