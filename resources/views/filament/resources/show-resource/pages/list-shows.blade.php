@extends(\Filament\Support\Facades\FilamentView::getDefaultLayout())

@section('content')
    @yield('content')
@endsection

@push('scripts')
    <div id="show-log-panel-container">
        <livewire:show-streamer-log-panel />
    </div>

    <script>
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('[data-table-action="open_log"]');
            if (!btn) return;

            const row = btn.closest('tr');
            if (!row) return;

            const recordKey = row.getAttribute('data-record-key');
            if (recordKey) {
                Livewire.dispatch('open-show', { show: recordKey });
            }
        });
    </script>
@endpush
