@if(\App\Services\TeamCommunications::canAccess(auth()->user()))
    <livewire:quick-messages />
@endif
