<?php

namespace App\Filament\Widgets;

use App\Models\InventoryMovement;
use App\Models\ShowChangeLog;
use App\Models\ShowIngestionLog;
use App\Models\StreamerLogEntry;
use Carbon\Carbon;
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
            ->limit(60)
            ->get()
            ->filter(fn ($change) => $this->isMaterialChange($change->old_value, $change->new_value))
            ->map(function ($change) {
                $field = (string) $change->field_name;

                return [
                    'at' => $change->created_at,
                    'type' => 'whatnot_change',
                    'title' => $this->fieldLabel($field) . ' changed',
                    'field' => $this->fieldLabel($field),
                    'old' => $this->formatValue($field, $change->old_value),
                    'new' => $this->formatValue($field, $change->new_value),
                    'detail' => null,
                    'meta' => $this->sourceLabel((string) ($change->source ?: 'system')) . ' · ' . ($change->changed_by ?: 'system'),
                ];
            });

        $ingestion = ShowIngestionLog::query()
            ->with('channel')
            ->where('show_id', $showId)
            ->latest('created_at')
            ->limit(30)
            ->get()
            ->map(function (ShowIngestionLog $log) {
                $captured = $log->capturedFields();
                $detail = $captured !== []
                    ? collect($captured)->take(5)->map(fn ($value, $label) => $label . ': ' . $value)->implode(' · ')
                    : $log->summary();

                if ($log->status === 'failed' && filled($log->error_message)) {
                    $detail = $log->error_message;
                }

                return [
                    'at' => $log->created_at,
                    'type' => 'ingestion',
                    'title' => $log->sourceLabel(),
                    'field' => null,
                    'old' => null,
                    'new' => null,
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
                'field' => null,
                'old' => null,
                'new' => null,
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
                'field' => null,
                'old' => null,
                'new' => null,
                'detail' => ($log->streamer?->name ?? 'Streamer') . ' submitted the post-show inventory report.',
                'meta' => null,
            ]);
        }

        if ($log?->reviewed_at) {
            $reportEvents->push([
                'at' => $log->reviewed_at,
                'type' => 'review',
                'title' => $log->approval_status === 'approved' ? 'Show report approved' : 'Show report reviewed',
                'field' => null,
                'old' => null,
                'new' => null,
                'detail' => $log->reviewedBy?->name ? 'Reviewed by ' . $log->reviewedBy->name : 'Approved by workflow automation.',
                'meta' => $log->approval_notes,
            ]);
        }

        return $changes
            ->concat($ingestion)
            ->concat($movements)
            ->concat($reportEvents)
            ->sortByDesc(fn ($event) => $event['at']?->timestamp ?? 0)
            ->take(80)
            ->values();
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'gross_revenue' => 'Gross Revenue',
            'whatnot_net' => 'Estimated Net',
            'completed_earnings' => 'Completed Earnings',
            'avg_order_value' => 'Average Order Value',
            'giveaway_spend' => 'Giveaway Spend',
            'giveaways_count' => 'Giveaways',
            'buyers_count' => 'Buyers',
            'first_time_buyers' => 'First-time Buyers',
            'returning_buyers' => 'Returning Buyers',
            'shares_count' => 'Shares',
            'show_duration' => 'Show Duration',
            'max_concurrent_viewers' => 'Peak Viewers',
            'total_views' => 'Total Views',
            'avg_order_rating' => 'Average Order Rating',
            'show_date' => 'Show Date',
            'start_time' => 'Start Time',
            'shipment_dimensions_json' => 'Shipment Package Dimensions',
            default => ucwords(str_replace('_', ' ', $field)),
        };
    }

    private function sourceLabel(string $source): string
    {
        return match ($source) {
            'whatnot_import' => 'Whatnot import',
            'whatnot_shipment_import' => 'Whatnot shipment sync',
            'manual' => 'Manual change',
            default => ucwords(str_replace('_', ' ', $source)),
        };
    }

    private function formatValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') return '—';

        if (in_array($field, ['gross_revenue', 'whatnot_net', 'completed_earnings', 'avg_order_value', 'giveaway_spend'], true) && is_numeric($value)) {
            return '$' . number_format((float) $value, 2);
        }

        if (in_array($field, ['giveaways_count', 'buyers_count', 'first_time_buyers', 'returning_buyers', 'shares_count', 'max_concurrent_viewers', 'total_views', 'units_sold'], true) && is_numeric($value)) {
            return number_format((float) $value);
        }

        if ($field === 'show_date') {
            try { return Carbon::parse((string) $value)->format('M j, Y'); } catch (\Throwable) {}
        }

        if ($field === 'start_time') {
            try { return Carbon::parse((string) $value)->format('M j, Y g:i A'); } catch (\Throwable) {}
        }

        $decoded = $this->decodeJson($value);
        if (is_array($decoded)) {
            $count = count($decoded);
            return $count . ' ' . str('record')->plural($count);
        }

        return (string) $value;
    }

    private function isMaterialChange(mixed $old, mixed $new): bool
    {
        $oldJson = $this->decodeJson($old);
        $newJson = $this->decodeJson($new);

        if (is_array($oldJson) && is_array($newJson)) {
            return $this->normalizeArray($oldJson) !== $this->normalizeArray($newJson);
        }

        return (string) ($old ?? '') !== (string) ($new ?? '');
    }

    private function decodeJson(mixed $value): ?array
    {
        if (! is_string($value) || trim($value) === '') return null;
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : null;
    }

    private function normalizeArray(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) $value[$key] = $this->normalizeArray($item);
        }
        ksort($value);
        return $value;
    }
}
