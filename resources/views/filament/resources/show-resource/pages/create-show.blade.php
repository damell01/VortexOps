@php use Filament\Support\Enums\MaxWidth; @endphp

<x-filament-panels::page>
    <!-- Livewire modal component -->
    @livewire('show-items-modal', ['showId' => null], key('show-items-modal'))

    <!-- Main form content -->
    <div id="create-show-form">
        {{ $this->form }}
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Initialize modal handler - dispatch event to all Livewire components
            window.openShowItemsModalHandler = function() {
                Livewire.dispatch('openShowItemsModal');
            };

            // Handle items selected from modal via Livewire event
            Livewire.on('itemsSelected', (payload) => {
                let items = [];
                let locationId = null;

                // Handle different payload formats from Livewire
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
                // Wait for DOM to be ready
                setTimeout(() => {
                    items.forEach((item) => {
                        // Click the add button to create a new row
                        const addBtn = document.querySelector('button[wire\\:click*="addAction"][wire\\:click*="inventory_items"]');

                        if (!addBtn) {
                            // Alternative: find by aria-label or text content
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

                        // After a short delay, fill in the new row
                        setTimeout(() => {
                            fillLastRepeaterRow(item, locationId);
                        }, 150);
                    });
                }, 100);
            }

            function fillLastRepeaterRow(item, locationId) {
                // Find all repeater rows (they typically have data-repeater-index or similar)
                const rows = document.querySelectorAll('[data-form-repeater-item]');

                if (rows.length === 0) {
                    // Try alternative selector
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
                // Find input fields in this row
                const inputs = row.querySelectorAll('input[type="number"], input[type="text"], select');

                if (inputs.length >= 3) {
                    // Usually: product_id (select), location (select), quantity, cost
                    // Find the right inputs by their placeholder or nearby labels

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
