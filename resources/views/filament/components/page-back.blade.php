<div class="vx-page-back-row">
    <a href="{{ \App\Filament\Pages\DashboardImproved::getUrl(panel: 'admin') }}"
       class="vx-page-back" aria-label="Back to previous page"
       onclick="if(window.vortexGoBack){event.preventDefault();window.vortexGoBack(this.href)}else if(history.length&gt;1){event.preventDefault();history.back()}">
        <x-filament::icon icon="heroicon-o-arrow-left" />
        <span>Back</span>
    </a>
</div>
<style>
.vx-page-back-row{box-sizing:border-box;width:100%;margin-block:0 -12px;padding-inline:32px}
.vx-page-back{display:inline-flex;align-items:center;gap:6px;min-height:44px;padding:4px 0;color:var(--vx-muted,#64748b);font-size:13px;font-weight:600;text-decoration:none}
.vx-page-back svg{width:18px;height:18px;flex:none}
.vx-page-back:hover{color:#7c3aed}
.vx-page-back:focus-visible{outline:3px solid #a78bfa;outline-offset:3px;border-radius:4px}
@media(max-width:1023px){.vx-page-back-row{padding-inline:24px}}
@media(max-width:639px){.vx-page-back-row{padding-inline:16px;margin-bottom:-8px}}
</style>
