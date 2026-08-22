<?php

namespace App\Filament\Widgets;

use App\Models\StreamerLogEntry;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class ShowStreamerLogWidget extends Widget
{
    protected static ?int $sort = 10;
    protected int|string|array $columnSpan = 'full';
    protected string $view = 'filament.widgets.show-streamer-log';

    public ?Model $record = null;
    public bool $showRejectForm = false;
    public string $rejectionNotes = '';

    public static function canView(): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() ?? false) || ($user?->isOwner() ?? false);
    }

    public function getLog(): ?StreamerLogEntry
    {
        if (! $this->record) return null;

        return StreamerLogEntry::query()
            ->with(['streamer', 'items.inventoryItem', 'items.location', 'reviewedBy'])
            ->where('show_id', $this->record->getKey())
            ->first();
    }

    public function getProblemsProperty(): array
    {
        $log = $this->getLog();
        return $log?->inventoryPostingProblems() ?? [];
    }

    public function approveReport(): void
    {
        abort_unless(static::canView(), 403);
        $log = $this->getLog();
        if (! $log) return;

        if ($log->items()->whereNull('inventory_item_id')->exists()) {
            Notification::make()
                ->title('Unmatched items remain')
                ->body('Match all unlisted items before approving this report.')
                ->warning()
                ->send();
            return;
        }

        $problems = $log->approveByAdmin();
        $notification = Notification::make()
            ->title($problems === [] ? 'Show report approved' : 'Approved with inventory exceptions')
            ->body($problems === [] ? 'The report is approved and the workflow can continue.' : implode("\n", $problems));

        if ($problems === []) {
            $notification->success();
        } else {
            $notification->warning();
        }

        $notification->send();
    }

    public function toggleRejectForm(): void
    {
        abort_unless(static::canView(), 403);
        $this->showRejectForm = ! $this->showRejectForm;
    }

    public function rejectReport(): void
    {
        abort_unless(static::canView(), 403);
        $notes = trim($this->rejectionNotes);

        if ($notes === '') {
            Notification::make()->title('Add a reason for the streamer')->warning()->send();
            return;
        }

        $log = $this->getLog();
        if (! $log) return;

        $log->rejectByAdmin($notes);
        $this->showRejectForm = false;
        $this->rejectionNotes = '';

        Notification::make()->title('Changes requested')->body('The streamer can revise the report.')->warning()->send();
    }
}
