@php
    $record = $getRecord();
    [$label, $tone, $note] = match (true) {
        $record->hasPendingRevisionRequest() => ['Edit requested', 'rose', $record->revision_reason ?: 'No reason given'],
        $record->status === 'streamer_reviewed' => ['Ready for review', 'violet', 'Submitted by the streamer'],
        $record->status === 'changes_requested' => ['Changes requested', 'amber', 'Waiting on the streamer'],
        $record->status === 'admin_approved' => ['Approved', 'green', $record->needsFulfillmentReview() ? 'Needs fulfillment review' : ($record->reviewed_at ? 'Approved ' . $record->reviewed_at->format('M j') : 'Ready for payroll')],
        default => ['In progress', 'gray', 'Streamer is still logging'],
    };
@endphp
<div class="vxi-status">
    <span class="vxi-pill vxi-{{ $tone }}">{{ $label }}</span>
    <div class="vxi-note" title="{{ $note }}">{{ $note }}</div>
</div>
