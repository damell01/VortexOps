<style>
/* Inventory item detail page contrast/readability pass.
   The detail view uses a custom dark card layout even when the panel itself is
   light. Keep every label/value readable in both themes and on mobile. */
.vx-item-shell{color:rgb(15 23 42)}
.dark .vx-item-shell{color:rgb(248 250 252)}

.vx-item-shell .vx-card{color:rgb(15 23 42)}
.dark .vx-item-shell .vx-card{color:rgb(248 250 252)}

.vx-item-shell .text-gray-500{color:rgb(71 85 105)!important}
.vx-item-shell .text-gray-400{color:rgb(100 116 139)!important}
.dark .vx-item-shell .text-gray-500{color:rgb(203 213 225)!important}
.dark .vx-item-shell .text-gray-400{color:rgb(203 213 225)!important}

.vx-item-shell .vx-stat-label,
.vx-item-shell .vx-empty,
.vx-item-shell .vx-table th{color:rgb(71 85 105)!important}
.dark .vx-item-shell .vx-stat-label,
.dark .vx-item-shell .vx-empty,
.dark .vx-item-shell .vx-table th{color:rgb(203 213 225)!important}

.dark .vx-item-shell .vx-card,
.dark .vx-item-shell .vx-stat,
.dark .vx-item-shell .vx-metric,
.dark .vx-item-shell .vx-summary-grid>div{
    border-color:rgb(71 85 105)!important;
}

.dark .vx-item-shell .vx-card{background:rgb(15 23 42)!important}
.dark .vx-item-shell .vx-tabs{background:rgb(15 23 42)!important;border-color:rgb(71 85 105)!important}
.dark .vx-item-shell .vx-table td{border-color:rgb(51 65 85)!important}

.dark .vx-item-shell .vx-pill{
    background:rgb(51 65 85)!important;
    color:rgb(241 245 249)!important;
}

.dark .vx-item-shell a:not(.bg-emerald-600){color:rgb(216 180 254)}
.dark .vx-item-shell .text-primary-400,
.dark .vx-item-shell .text-primary-500{color:rgb(216 180 254)!important}

.dark .vx-item-shell .text-emerald-400{color:rgb(110 231 183)!important}
.dark .vx-item-shell .text-red-500{color:rgb(252 165 165)!important}

.dark .vx-item-shell .bg-gray-800{background:rgb(51 65 85)!important}
.dark .vx-item-shell .border-gray-700,
.dark .vx-item-shell .border-gray-800{border-color:rgb(71 85 105)!important}

/* The screenshot showed several labels effectively disappearing because dark
   text utility classes were being rendered over dark cards. These explicit
   fallbacks cover unqualified spans/divs inside the custom cards too. */
.dark .vx-item-shell .vx-card h1,
.dark .vx-item-shell .vx-card h2,
.dark .vx-item-shell .vx-card h3,
.dark .vx-item-shell .vx-card h4,
.dark .vx-item-shell .vx-card b,
.dark .vx-item-shell .vx-card strong,
.dark .vx-item-shell .vx-card td,
.dark .vx-item-shell .vx-card .vx-stat-value{
    color:rgb(248 250 252);
}

@media(max-width:640px){
    .dark .vx-item-shell .vx-card,
    .dark .vx-item-shell .vx-tabs{
        box-shadow:0 1px 0 rgba(148,163,184,.08),0 8px 28px rgba(2,6,23,.18);
    }
    .dark .vx-item-shell .vx-metric,
    .dark .vx-item-shell .vx-summary-grid>div{
        background:rgb(17 24 39);
    }
}
</style>
