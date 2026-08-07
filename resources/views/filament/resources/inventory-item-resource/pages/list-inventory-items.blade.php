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

    <!-- Mobile enhancements -->
    <x-infinite-scroll
        load-more-url="{{ route('filament.admin.resources.inventory-items.index') }}"
        page-param="page"
        class="hidden"
    >
        <!-- Content loaded via infinite scroll -->
    </x-infinite-scroll>

    <!-- Bottom action bar for bulk operations -->
    <x-bottom-action-bar>
        <button
            class="bottom-action-bar-action bottom-action-bar-action-primary"
            onclick="exportSelected()"
        >
            📤 Export
        </button>
        <button
            class="bottom-action-bar-action bottom-action-bar-action-danger"
            onclick="deleteSelected()"
        >
            🗑️ Delete
        </button>
    </x-bottom-action-bar>

    <!-- Floating action button -->
    <x-floating-action-button label="Quick Actions" icon="⚡">
        <a href="{{ route('filament.admin.resources.inventory-items.quick-add') }}"
           class="floating-action-menu-item">
            ⚡ Quick Add
        </a>
        <a href="{{ route('filament.admin.resources.inventory-items.create') }}"
           class="floating-action-menu-item">
            ✏️ Full Form
        </a>
        <a href="{{ route('export.inventory-items') }}"
           class="floating-action-menu-item">
            📊 Export
        </a>
    </x-floating-action-button>
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
