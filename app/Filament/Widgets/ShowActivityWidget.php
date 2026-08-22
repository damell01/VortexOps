<?php

namespace App\Filament\Widgets;

use App\Models\InventoryMovement;
use App\Models\ShowChangeLog;
use App\Models\StreamerLogEntry;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ShowActivityWidget extends Widget
{
    protected static ?int $sort = 20;
    protected int|string|array $columnSpan = 'full';
    protected string $view = 'filament.widgets.show-activity';

    public ?Model $record = null;

    public static function canView(): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() || $user?->isOwner()) ?? false;
    }

    public function getEventsProperty(): Collection
    {
        if (! $this->record) return collect();
        $showId = $this->record->getKey();

        $changes = ShowChangeLog::query()
            ->where('show_id', $showId)
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($change) => [
                'at' => $change->created_at,
                'type' => 'whatnot_change',
                'title' => ucwords(str_replace('_', ' ', $change->field_name)) . ' changed',
                'detail' => $this->formatChange($change->old_value, $change->new_value),
                'meta' => ucfirst((string)($change->source ?: 'system')) . ' · ' . ($change->changed_by ?: 'system'),
            ]);

        $movements = InventoryMovement::query()
            ->with(['item', 'createdByUser'])
            ->where('reference_type', 'show')
            ->where('reference_id', $showId)
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn ($movement) => [
                'at' => $movement->created_at,
                'type' => 'inventory',
                'title' => InventoryMovement::movementTypeLabels()[$movement->movement_type] ?? ucwords(str_replace('_', ' ', $movement->movement_type)),
                'detail' => $movement->changeLabel() . ' ' . ($movement->item?->name ?? 'inventory item'),
                'meta' => $movement->reason ?: ($movement->createdByUser?->name ?? 'system'),
            ]);

        $log = StreamerLogEntry::query()->with(['streamer', 'reviewedBy'])->where('show_id', $showId)->first();
        $reportEvents = collect();

        if ($log?->submitted_at) {
            $reportEvents->push([
                'at' => $log->submitted_at,
                'type' => 'report',
                'title' => 'Streamer report submitted',
                'detail' => ($log->streamer?->name ?? 'Streamer') . ' submitted the post-show inventory report.',
                'meta' => null,
            ]);
        }

        if ($log?->reviewed_at) {
            $reportEvents->push([
                'at' => $log->reviewed_at,
                'type' => 'review',
                'title' => $log->approval_status === 'approved' ? 'Show report approved' : 'Show report reviewed',
                'detail' => $log->reviewedBy?->name ? 'Reviewed by ' . $log->reviewedBy->name : 'Approved by workflow automation.',
                'meta' => $log->approval_notes,
            ]);
        }

        return $changes
            ->concat($movements)
            ->concat($reportEvents)
            ->sortByDesc(fn ($event) => $event['at']?->timestamp ?? 0)
            ->take(40)
            ->values();
    }

    private function formatChange(?string $old, ?string $new): string
    {
        $old = $old === null || $old === '' ? '—' : $old;
        $new = $new === null || $new === '' ? '—' : $new;
        return $old . ' → ' . $new;
    }
}
