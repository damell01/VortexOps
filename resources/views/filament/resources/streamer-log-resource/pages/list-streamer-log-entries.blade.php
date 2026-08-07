@php
use Filament\Support\Enums\MaxWidth;
@endphp

<x-filament-panels::page>
    <!-- Skeleton loader for initial page load -->
    <div wire:loading.delay class="space-y-4">
        <x-skeleton-loader type="table" :columns="4" :rows="3" />
    </div>

    <!-- Actual content -->
    <div wire:loading.delay.remove>
        {{ $this->table }}
    </div>

    <!-- Bottom action bar for bulk operations on mobile -->
    <x-bottom-action-bar>
        <button
            class="bottom-action-bar-action bottom-action-bar-action-primary"
            onclick="approveSelected()"
            title="Approve selected logs"
        >
            ✓ Approve
        </button>
        <button
            class="bottom-action-bar-action bottom-action-bar-action-danger"
            onclick="rejectSelected()"
            title="Reject selected logs"
        >
            ✗ Reject
        </button>
        <button
            class="bottom-action-bar-action bottom-action-bar-action-secondary"
            onclick="exportSelected()"
            title="Export selected logs"
        >
            📤 Export
        </button>
    </x-bottom-action-bar>

    <!-- Floating action button for quick add -->
    <x-floating-action-button label="New Log" icon="📝">
        <a href="{{ route('filament.admin.resources.streamer-logs.create') }}"
           class="floating-action-menu-item">
            ➕ New Log Entry
        </a>
        <a href="{{ route('filament.admin.resources.shows.index') }}"
           class="floating-action-menu-item">
            🎬 View Shows
        </a>
        <a href="{{ route('filament.admin.resources.streamers.index') }}"
           class="floating-action-menu-item">
            🎤 View Streamers
        </a>
    </x-floating-action-button>
</x-filament-panels::page>

<script>
function approveSelected() {
    const selected = Array.from(document.querySelectorAll('input[type="checkbox"]:checked'))
        .map(cb => cb.closest('tr')?.dataset.logId)
        .filter(Boolean);

    if (selected.length === 0) {
        alert('Please select logs to approve');
        return;
    }

    if (!confirm(`Approve ${selected.length} log(s)?`)) return;

    fetch('/admin/api/streamer-logs/approve-bulk', {
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
            alert(`${data.count} log(s) approved`);
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Could not approve logs'));
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error approving logs');
    });
}

function rejectSelected() {
    const selected = Array.from(document.querySelectorAll('input[type="checkbox"]:checked'))
        .map(cb => cb.closest('tr')?.dataset.logId)
        .filter(Boolean);

    if (selected.length === 0) {
        alert('Please select logs to reject');
        return;
    }

    const reason = prompt(`Rejection reason for ${selected.length} log(s):`);
    if (!reason) return;

    fetch('/admin/api/streamer-logs/reject-bulk', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ ids: selected, reason })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(`${data.count} log(s) rejected`);
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Could not reject logs'));
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error rejecting logs');
    });
}

function exportSelected() {
    const selected = Array.from(document.querySelectorAll('input[type="checkbox"]:checked'))
        .map(cb => cb.closest('tr')?.dataset.logId)
        .filter(Boolean);

    if (selected.length === 0) {
        alert('Please select logs to export');
        return;
    }

    // Create download link
    const downloadLink = document.createElement('a');
    downloadLink.href = '/admin/export/streamer-logs?ids=' + selected.join(',');
    downloadLink.download = true;
    downloadLink.click();
}
</script>
