<?php

namespace App\Filament\Resources\FulfillmentResource\Pages;

use App\Filament\Resources\FulfillmentResource;
use App\Models\Show;
use App\Models\StreamerLogItem;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ListFulfillmentShows extends ListRecords
{
    protected static string $resource = FulfillmentResource::class;

    public function getView(): string
    {
        return 'filament.resources.fulfillment-resource.pages.list-fulfillment-shows';
    }

    public function getTitle(): string
    {
        return 'Fulfillment Center';
    }

    public function getSubheading(): ?string
    {
        $user = auth()->user();

        return ($user?->isFulfillment() && ! $user->isAdmin() && ! $user->isFulfillmentAdmin())
            ? 'Your assigned approved shows. Pack exactly what the streamer logged, resolve exceptions, then complete fulfillment.'
            : 'Approved streamer reports move here. Assign the show, then fulfillment works the streamer-logged item list.';
    }

    /** @return \Illuminate\Support\Collection<int, array<string,mixed>> */
    public function getQueueCards()
    {
        // Do not sort in SQL by fulfillment_reviewed_at. Some older production
        // databases were created before that optional signoff column existed,
        // which made the whole Fulfillment Center 500 before a user could pack.
        return FulfillmentResource::getEloquentQuery()
            ->orderByDesc('show_date')
            ->limit(24)
            ->get()
            ->map(function (Show $show): array {
                $items = $show->streamerLogEntry?->items ?? collect();
                $totalLines = $items->count();
                $totalUnits = (int) $items->sum('quantity');
                $packedUnits = min($totalUnits, (int) $items->sum('packed_quantity'));
                $remainingUnits = max(0, $totalUnits - $packedUnits);
                $issues = $items->filter(fn (StreamerLogItem $item) => $item->fulfillmentStatus() === StreamerLogItem::FULFILLMENT_NOT_FULFILLED)->count();
                $completed = $this->isCompleted($show, $remainingUnits, $issues);
                $assigned = $show->fulfillmentUsers->isNotEmpty();

                if ($completed) {
                    $stage = 'Completed';
                    $tone = 'success';
                    $action = 'View Completed';
                } elseif (! $assigned) {
                    $stage = 'Needs Assignment';
                    $tone = 'warning';
                    $action = 'Review Show';
                } elseif ($issues > 0) {
                    $stage = 'Issues';
                    $tone = 'danger';
                    $action = 'Resolve Issues';
                } elseif ($remainingUnits > 0 && $packedUnits > 0) {
                    $stage = 'Packing';
                    $tone = 'primary';
                    $action = 'Continue Packing';
                } elseif ($remainingUnits > 0) {
                    $stage = 'Ready to Pack';
                    $tone = 'info';
                    $action = 'Start Packing';
                } else {
                    $stage = 'Ready to Complete';
                    $tone = 'success';
                    $action = 'Complete Fulfillment';
                }

                return [
                    'show' => $show,
                    'stage' => $stage,
                    'tone' => $tone,
                    'action' => $action,
                    'total_lines' => $totalLines,
                    'total_units' => $totalUnits,
                    'packed_units' => $packedUnits,
                    'remaining_units' => $remainingUnits,
                    'issues' => $issues,
                    'progress' => $totalUnits > 0 ? min(100, (int) round(($packedUnits / $totalUnits) * 100)) : 0,
                ];
            })
            ->sortBy(fn (array $card): int => match ($card['stage']) {
                'Needs Assignment' => 0,
                'Issues' => 1,
                'Packing' => 2,
                'Ready to Pack' => 3,
                'Ready to Complete' => 4,
                default => 5,
            })
            ->values();
    }

    public function getDefaultActiveTab(): string|int|null
    {
        $user = auth()->user();
        return ($user?->isFulfillment() && ! $user->isAdmin() && ! $user->isFulfillmentAdmin()) ? 'my_work' : 'active';
    }

    public function getTabs(): array
    {
        $user = auth()->user();
        $isFulfillmentOnly = $user?->isFulfillment() && ! $user?->isAdmin() && ! $user?->isFulfillmentAdmin();
        $hasSignoff = $this->hasSignoffColumn();

        $activeScope = function (Builder $query) use ($hasSignoff): Builder {
            if ($hasSignoff) {
                return $query->whereHas('streamerLogEntry', fn (Builder $log) => $log->whereNull('fulfillment_reviewed_at'));
            }

            // Legacy fallback: unfinished means at least one logged line still
            // has quantity remaining or is explicitly marked as an issue.
            return $query->where(function (Builder $work) {
                $work->whereHas('streamerLogEntry.items', fn (Builder $items) => $items->whereColumn('packed_quantity', '<', 'quantity'))
                    ->orWhereHas('streamerLogEntry.items', fn (Builder $items) => $items->where('fulfillment_status', StreamerLogItem::FULFILLMENT_NOT_FULFILLED));
            });
        };

        $completedScope = function (Builder $query) use ($hasSignoff): Builder {
            if ($hasSignoff) {
                return $query->whereHas('streamerLogEntry', fn (Builder $log) => $log->whereNotNull('fulfillment_reviewed_at'));
            }

            return $query
                ->whereDoesntHave('streamerLogEntry.items', fn (Builder $items) => $items->whereColumn('packed_quantity', '<', 'quantity'))
                ->whereDoesntHave('streamerLogEntry.items', fn (Builder $items) => $items->where('fulfillment_status', StreamerLogItem::FULFILLMENT_NOT_FULFILLED));
        };

        if ($isFulfillmentOnly) {
            return [
                'my_work' => Tab::make('My Active Work')
                    ->icon('heroicon-m-user')
                    ->modifyQueryUsing(function (Builder $query) use ($user, $activeScope): Builder {
                        $query->whereHas('fulfillmentUsers', fn (Builder $users) => $users->where('users.id', $user->id));
                        return $activeScope($query);
                    }),
                'issues' => Tab::make('Issues')
                    ->icon('heroicon-m-exclamation-triangle')
                    ->modifyQueryUsing(fn (Builder $query) => $query
                        ->whereHas('streamerLogEntry.items', fn (Builder $items) => $items->where('fulfillment_status', StreamerLogItem::FULFILLMENT_NOT_FULFILLED))),
                'completed' => Tab::make('Completed')
                    ->icon('heroicon-m-check-circle')
                    ->modifyQueryUsing($completedScope),
            ];
        }

        return [
            'active' => Tab::make('Active Fulfillment')
                ->icon('heroicon-m-inbox-stack')
                ->modifyQueryUsing($activeScope),
            'needs_assignment' => Tab::make('Needs Assignment')
                ->icon('heroicon-m-user-plus')
                ->modifyQueryUsing(function (Builder $query) use ($activeScope): Builder {
                    $query->whereDoesntHave('fulfillmentUsers');
                    return $activeScope($query);
                }),
            'packing' => Tab::make('Packing')
                ->icon('heroicon-m-cube')
                ->modifyQueryUsing(function (Builder $query) use ($activeScope): Builder {
                    $query->whereHas('fulfillmentUsers');
                    return $activeScope($query);
                }),
            'issues' => Tab::make('Issues')
                ->icon('heroicon-m-exclamation-triangle')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereHas('streamerLogEntry.items', fn (Builder $items) => $items->where('fulfillment_status', StreamerLogItem::FULFILLMENT_NOT_FULFILLED))),
            'completed' => Tab::make('Completed')
                ->icon('heroicon-m-check-circle')
                ->modifyQueryUsing($completedScope),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    private function hasSignoffColumn(): bool
    {
        return Schema::hasColumn('streamer_log_entries', 'fulfillment_reviewed_at');
    }

    private function isCompleted(Show $show, int $remainingUnits, int $issues): bool
    {
        if ($this->hasSignoffColumn()) {
            return $show->streamerLogEntry?->fulfillment_reviewed_at !== null;
        }

        return ($show->streamerLogEntry?->items->isNotEmpty() ?? false)
            && $remainingUnits === 0
            && $issues === 0;
    }
}
