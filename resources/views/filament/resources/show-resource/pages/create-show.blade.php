@php use Filament\Support\Enums\MaxWidth; @endphp

<x-filament-panels::page>
    <style>
        #create-show-form{max-width:1100px;margin:0 auto}
        @media(max-width:640px){
            body:has(#create-show-form) .fi-page-header{margin-bottom:.75rem!important}
            body:has(#create-show-form) .fi-page-header-heading{font-size:1.45rem!important;line-height:1.2!important}
            #create-show-form{max-width:none;margin:0;padding-bottom:5.5rem}
            #create-show-form .fi-section{border-radius:.85rem!important;margin-bottom:.7rem!important;overflow:hidden}
            #create-show-form .fi-section-header{padding:.85rem!important}#create-show-form .fi-section-content{padding:.85rem!important}
            #create-show-form .fi-sc-grid,#create-show-form [style*="grid-template-columns"]{grid-template-columns:minmax(0,1fr)!important}
            #create-show-form .fi-fo-field-wrp,#create-show-form .fi-fo-component-ctn>*{min-width:0!important;grid-column:1/-1!important}
            #create-show-form input,#create-show-form select,#create-show-form textarea,#create-show-form button[role="combobox"]{font-size:16px!important;min-height:46px!important}
            #create-show-form .fi-fo-repeater-item{border-radius:.8rem!important}#create-show-form .fi-fo-repeater-item-content{padding:.75rem!important}
            #create-show-form .fi-wizard-header{overflow-x:auto!important;-webkit-overflow-scrolling:touch}#create-show-form .fi-wizard-steps{min-width:max-content!important}
            body:has(#create-show-form) .fi-form-actions{position:sticky!important;bottom:0!important;z-index:30!important;margin-inline:-1rem!important;padding:.65rem 1rem max(.65rem,env(safe-area-inset-bottom))!important;border-top:1px solid rgb(229 231 235)!important;background:rgba(255,255,255,.96)!important;backdrop-filter:blur(16px)}
            .dark body:has(#create-show-form) .fi-form-actions{border-color:rgb(55 65 81)!important;background:rgba(17,24,39,.96)!important}
            body:has(#create-show-form) .fi-form-actions .fi-btn{min-height:48px!important;flex:1!important}
        }
    </style>

    @livewire('show-items-modal', ['showId' => null], key('show-items-modal'))

    <div id="create-show-form">
        {{ $this->form }}
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            window.openShowItemsModalHandler = function() {
                Livewire.dispatch('openShowItemsModal');
            };

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    Livewire.dispatch('closeShowItemsModal');
                }
            });

            Livewire.on('itemsSelected', (payload) => {
                let items = [];
                let locationId = null;

                if (Array.isArray(payload) && payload.length > 0) {
                    const data = payload[0];
                    items = data.items || [];
                    locationId = data.locationId;
                } else if (payload && typeof payload === 'object') {
                    items = payload.items || [];
                    locationId = payload.locationId;
                }

                if (items.length > 0) {
                    addItemsToRepeater(items, locationId);
                }
            });

            function addItemsToRepeater(items, locationId) {
                setTimeout(() => {
                    items.forEach((item) => {
                        const addBtn = document.querySelector('button[wire\\:click*="addAction"][wire\\:click*="inventory_items"]');

                        if (!addBtn) {
                            const buttons = Array.from(document.querySelectorAll('button'));
                            const addButton = buttons.find(btn =>
                                btn.textContent.includes('Add item') ||
                                btn.getAttribute('wire:click')?.includes('addAction')
                            );

                            if (addButton) {
                                addButton.click();
                            }
                        } else {
                            addBtn.click();
                        }

                        setTimeout(() => {
                            fillLastRepeaterRow(item, locationId);
                        }, 150);
                    });
                }, 100);
            }

            function fillLastRepeaterRow(item, locationId) {
                const rows = document.querySelectorAll('[data-form-repeater-item]');

                if (rows.length === 0) {
                    const containers = document.querySelectorAll('.fi-repeater-item, [class*="repeater"]');
                    if (containers.length > 0) {
                        const lastRow = containers[containers.length - 1];
                        populateRowFields(lastRow, item, locationId);
                    }
                    return;
                }

                const lastRow = rows[rows.length - 1];
                populateRowFields(lastRow, item, locationId);
            }

            function populateRowFields(row, item, locationId) {
                const inputs = row.querySelectorAll('input[type="number"], input[type="text"], select');

                if (inputs.length >= 3) {
                    const productSelect = row.querySelector('select') || inputs[0];
                    const locationSelect = Array.from(inputs).find(inp => inp.tagName === 'SELECT' && inp !== productSelect);
                    const quantityInput = row.querySelector('input[wire\\:model*="quantity"]') || inputs[inputs.length - 2];
                    const costInput = row.querySelector('input[wire\\:model*="cost"]') || inputs[inputs.length - 1];

                    if (productSelect && productSelect.tagName === 'SELECT') {
                        productSelect.value = item.id;
                        productSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    if (locationSelect && locationSelect.tagName === 'SELECT') {
                        locationSelect.value = locationId;
                        locationSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    if (quantityInput && quantityInput.tagName === 'INPUT') {
                        quantityInput.value = item.quantity;
                        quantityInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }

                    if (costInput && costInput.tagName === 'INPUT') {
                        costInput.value = parseFloat(item.unit_cost).toFixed(2);
                        costInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                }
            }
        });
    </script>
</x-filament-panels::page>
