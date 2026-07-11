<?php

namespace App\Filament\Resources\StreamerLogResource\Pages;

use App\Filament\Resources\StreamerLogResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListStreamerLogEntries extends ListRecords
{
    protected static string $resource = StreamerLogResource::class;

    /** Quick filter presets. The "To Review" count respects per-streamer scoping. */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),

            'to_review' => Tab::make('To Review')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'pending'))
                ->badge(StreamerLogResource::getEloquentQuery()->where('status', 'pending')->count())
                ->badgeColor('warning'),

            'reviewed' => Tab::make('Reviewed')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereIn('status', ['streamer_reviewed', 'admin_approved'])),

            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'admin_approved')),
        ];
    }
}
