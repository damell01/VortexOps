<?php

namespace App\Filament\Widgets;

use App\Models\InventoryMovement;
use App\Models\ShowChangeLog;
use App\Models\ShowIngestionLog;
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
            ->limit(30)
            ->get()
            ->map(fn ($change) => [
                'at' => $change->created_at,
                'type' => 'whatnot_change',
                'title' => ucwords(str_replace('_', ' ', $change->field_name)) . ' changed',
                'detail' => $this->formatChange($change->old_value, $change->new_value),
                'meta' => ucfirst((string)($change->source ?: 'system')) . ' · ' . ($change->changed_by ?: 'system'),
            ]);

        $ingestion = ShowIngestionLog::query()
            ->with('channel')
            ->where('show_id', $showId)
            ->latest('created_at')
            ->limit(30)
            ->get()
            ->map(function (ShowIngestionLog $log) {
                $payload = is_array($log->raw_payload) ? $log->raw_payload : [];
                $parts = [];

                foreach ([
                    'orders' => 'orders',
                    'units_sold' => 'units',
                    'shipment_count' => 'shipments',
                    'updated' => 'updates',
                    'created' => 'created',
                ] as $key => $label) {
                    if (isset($payload[$key]) && is_numeric($payload[$key])) {
                        $parts[] = number_format((float) $payload[$key]) . ' ' . $label;
                    }
                }

                $detail = $parts !== [] ? implode(' · ', $parts) : $log->summary();
                if ($log->status === 'failed' && filled($log->error_message)) {
                    $detail = $log->error_message;
                }

                return [
                    'at' => $log->created_at,
                    'type' => 'ingestion',
                    'title' => $log->sourceLabel(),
                    'detail' => $detail,
                    'meta' => trim(($log->channel?->name ? $log->channel->name . ' · ' : '') . ucfirst($log->status)),
                ];
            });

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
            ->concat($ingestion)
            ->concat($movements)
            ->concat($reportEvents)
            ->sortByDesc(fn ($event) => $event['at']?->timestamp ?? 0)
            ->take(60)
            ->values();
    }

    private function formatChange(?string $old, ?string $new): string
    {
        $old = $old === null || $old === '' ? '—' : $old;
        $new = $new === null || $new === '' ? '—' : $new;
        return $old . ' → ' . $new;
    }
}
