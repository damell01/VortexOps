<!-- Embed the Livewire component on the page -->
<div x-data>
    <livewire:show-streamer-log-panel />
</div>

<!-- Handle the "Log" action button clicks -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Listen for clicks on the "Log" table action button
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('[data-table-action="open_log"]');
            if (!btn) return;

            // Get the show ID from the closest table row
            const row = btn.closest('tr');
            if (!row) return;

            const recordKey = row.getAttribute('data-record-key');
            if (recordKey) {
                // Dispatch the Livewire event to open the show log panel
                Livewire.dispatch('open-show', { show: recordKey });
            }
        });
    });
</script>
