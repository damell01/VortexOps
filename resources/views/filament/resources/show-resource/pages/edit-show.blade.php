<x-filament-panels::page>
    <style>
        body:has(.vx-edit-shell) .fi-page-content{max-width:none!important;width:100%!important}
        .vx-edit-shell{max-width:none;width:100%;margin:0;padding:0 1rem}.vx-edit-intro{display:none}.vx-edit-shell form,.vx-edit-shell .fi-fo-component-ctn{width:100%;max-width:none}.vx-edit-shell .fi-section{width:100%;max-width:none;scroll-margin-top:6rem}
        @media(min-width:1024px){.vx-edit-shell{padding-inline:1.5rem}.vx-edit-shell .fi-section-content{padding:1.25rem 1.5rem!important}}
        @media(max-width:640px){
            body:has(.vx-edit-shell) .fi-page-header{display:none!important}
            .vx-edit-shell{margin:0;max-width:none;padding:0 0 5.5rem}
            .vx-edit-intro{display:block;margin-bottom:.75rem}
            .vx-edit-intro h1{font-size:1.45rem;font-weight:800;line-height:1.2;color:rgb(17 24 39)}
            .dark .vx-edit-intro h1{color:#fff}.vx-edit-intro p{margin-top:.35rem;font-size:.78rem;line-height:1.45;color:rgb(107 114 128)}
            .vx-edit-shell .fi-section{border-radius:.85rem!important;margin-bottom:.7rem!important;overflow:hidden}
            .vx-edit-shell .fi-section-header{padding:.85rem!important}.vx-edit-shell .fi-section-content{padding:.85rem!important}
            .vx-edit-shell .fi-sc-grid,.vx-edit-shell [style*="grid-template-columns"]{grid-template-columns:minmax(0,1fr)!important}
            .vx-edit-shell .fi-fo-field-wrp,.vx-edit-shell .fi-fo-component-ctn>*{min-width:0!important;grid-column:1/-1!important}
            .vx-edit-shell input,.vx-edit-shell select,.vx-edit-shell textarea,.vx-edit-shell button[role="combobox"]{font-size:16px!important;min-height:46px!important}
            .vx-edit-shell .fi-fo-repeater-item{border-radius:.8rem!important}.vx-edit-shell .fi-fo-repeater-item-content{padding:.75rem!important}
            .vx-edit-shell .fi-ac{gap:.5rem!important}.vx-edit-shell .fi-ac>.fi-btn,.vx-edit-shell .fi-ac>button{min-height:46px!important}
            body:has(.vx-edit-shell) .fi-form-actions{position:sticky!important;bottom:0!important;z-index:30!important;margin-inline:-1rem!important;padding:.65rem 1rem max(.65rem,env(safe-area-inset-bottom))!important;border-top:1px solid rgb(229 231 235)!important;background:rgba(255,255,255,.96)!important;backdrop-filter:blur(16px)}
            .dark body:has(.vx-edit-shell) .fi-form-actions{border-color:rgb(55 65 81)!important;background:rgba(17,24,39,.96)!important}
            body:has(.vx-edit-shell) .fi-form-actions .fi-btn{min-height:48px!important;flex:1!important}
        }
    </style>
    <div class="vx-edit-shell">
        <div class="vx-edit-intro">
            <h1>Edit Show</h1>
            <p>Update show details, assignment, timing, and operational settings. Fields stack into a single phone-friendly flow.</p>
        </div>
        <form wire:submit="save">
            {{ $this->form }}
            <div class="fi-form-actions mt-6 flex flex-wrap gap-3">
                <x-filament::button type="submit">
                    Save changes
                </x-filament::button>
                <x-filament::button color="gray" tag="a" :href="\App\Filament\Resources\ShowResource::getUrl('view', ['record' => $this->record])">
                    Cancel
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
