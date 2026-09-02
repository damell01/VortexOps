<x-filament-panels::page>
    <style>
        .vx-inventory-edit{max-width:1100px;margin:0 auto}.vx-inventory-edit-intro{display:none}
        @media(max-width:640px){
            body:has(.vx-inventory-edit) .fi-page-header{display:none!important}
            .vx-inventory-edit{max-width:none;margin:0;padding-bottom:5.5rem}
            .vx-inventory-edit-intro{display:block;margin-bottom:.75rem}.vx-inventory-edit-intro h1{font-size:1.45rem;font-weight:800;line-height:1.2;color:rgb(17 24 39)}.dark .vx-inventory-edit-intro h1{color:#fff}.vx-inventory-edit-intro p{margin-top:.35rem;font-size:.78rem;line-height:1.45;color:rgb(107 114 128)}
            .vx-inventory-edit .fi-section{border-radius:.85rem!important;margin-bottom:.7rem!important;overflow:hidden}.vx-inventory-edit .fi-section-header{padding:.85rem!important}.vx-inventory-edit .fi-section-content{padding:.85rem!important}
            .vx-inventory-edit .fi-sc-grid,.vx-inventory-edit [style*="grid-template-columns"]{grid-template-columns:minmax(0,1fr)!important}.vx-inventory-edit .fi-fo-field-wrp,.vx-inventory-edit .fi-fo-component-ctn>*{min-width:0!important;grid-column:1/-1!important}
            .vx-inventory-edit input,.vx-inventory-edit select,.vx-inventory-edit textarea,.vx-inventory-edit button[role="combobox"]{font-size:16px!important;min-height:46px!important}.vx-inventory-edit textarea{min-height:110px!important}
            .vx-inventory-edit .fi-fo-file-upload{min-height:120px}.vx-inventory-edit .fi-fo-repeater-item{border-radius:.8rem!important}.vx-inventory-edit .fi-fo-repeater-item-content{padding:.75rem!important}
            .vx-inventory-edit .vx-choice-cards{display:grid!important;grid-template-columns:1fr!important;gap:.5rem!important}.vx-inventory-edit .vx-choice-cards label{min-height:48px!important}
            body:has(.vx-inventory-edit) .fi-form-actions{position:sticky!important;bottom:0!important;z-index:30!important;margin-inline:-1rem!important;padding:.65rem 1rem max(.65rem,env(safe-area-inset-bottom))!important;border-top:1px solid rgb(229 231 235)!important;background:rgba(255,255,255,.96)!important;backdrop-filter:blur(16px)}
            .dark body:has(.vx-inventory-edit) .fi-form-actions{border-color:rgb(55 65 81)!important;background:rgba(17,24,39,.96)!important}body:has(.vx-inventory-edit) .fi-form-actions .fi-btn{min-height:48px!important;flex:1!important}
        }
    </style>
    <div class="vx-inventory-edit">
        <div class="vx-inventory-edit-intro">
            <h1>Edit Inventory Item</h1>
            <p>Update the product, barcode, pricing, case contents, and reorder settings. Stock quantity changes stay in Move / Correct Stock.</p>
        </div>
        <form wire:submit="save">
            {{ $this->form }}
            <div class="fi-form-actions mt-6 flex flex-wrap gap-3">
                @foreach ($this->getCachedFormActions() as $action)
                    {{ $action }}
                @endforeach
            </div>
        </form>
    </div>
</x-filament-panels::page>
