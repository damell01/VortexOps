@php
use Filament\Support\Enums\MaxWidth;
@endphp

<x-filament-panels::page>
    <!-- Skeleton loader for initial page load (shown via wire:loading) -->
    <div wire:loading.delay class="space-y-4">
        <x-skeleton-loader type="table" :columns="5" :rows="3" />
    </div>

    <!-- Actual content -->
    <div wire:loading.delay.remove>
        {{ $this->table }}
    </div>

    <!-- Bottom action bar for bulk and quick operations -->
    <div class="fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-3 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <span class="text-sm text-gray-600 dark:text-gray-400">0 items selected</span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('filament.admin.resources.inventory-items.quick-add') }}"
                       class="inline-flex items-center px-3 py-2 rounded-lg bg-primary-500 text-white hover:bg-primary-600 transition-colors text-sm font-medium">
                        Quick Add
                    </a>
                    <a href="{{ route('filament.admin.resources.inventory-items.create') }}"
                       class="inline-flex items-center px-3 py-2 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors text-sm font-medium">
                        Full Form
                    </a>
                    <a href="{{ route('export.inventory-items') }}"
                       class="inline-flex items-center px-3 py-2 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors text-sm font-medium">
                        Export
                    </a>
                    <button
                        class="inline-flex items-center px-3 py-2 rounded-lg bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-800 transition-colors text-sm font-medium"
                        onclick="deleteSelected()"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="h-20"></div>
</x-filament-panels::page>

<script>
function exportSelected() {
    const selected = Array.from(document.querySelectorAll('input[type="checkbox"]:checked'))
        .map(cb => {
            const row = cb.closest('tr');
            return row ? row.dataset.itemId : null;
        })
        .filter(Boolean);

    if (selected.length === 0) {
        alert('Please select items to export');
        return;
    }

    // Trigger Filament's export action
    const form = new FormData();
    selected.forEach(id => form.append('ids[]', id));

    const downloadLink = document.createElement('a');
    downloadLink.href = '{{ route('export.inventory-items') }}?ids=' + selected.join(',');
    downloadLink.click();
}

function deleteSelected() {
    const selected = Array.from(document.querySelectorAll('input[type="checkbox"]:checked'))
        .map(cb => {
            const row = cb.closest('tr');
            return row ? row.dataset.itemId : null;
        })
        .filter(Boolean);

    if (selected.length === 0) {
        alert('Please select items to delete');
        return;
    }

    if (!confirm(`Delete ${selected.length} items? This cannot be undone.`)) return;

    // Make delete request
    fetch('/admin/api/inventory-items/delete-bulk', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ ids: selected })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Items deleted successfully');
            location.reload();
        } else {
            alert('Error deleting items: ' + data.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error deleting items');
    });
}
</script>
