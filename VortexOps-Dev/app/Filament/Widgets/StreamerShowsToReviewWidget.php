<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\StreamerLogResource;
use App\Models\StreamerLogEntry;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * A streamer's actionable to-do list: the shows waiting for them to add their
 * items sold and costs, each with a one-click "Add items" jump straight into
 * the enrichment form. Streamer-only; admins have their own overview.
 */
class StreamerShowsToReviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected static ?string $heading = 'Shows to Review';
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();
        return ($user?->isStreamer() && ! $user->isAdmin() && ! $user->isOwner()) ?? false;
    }

    public function table(Table $table): Table
    {
        $streamerId = auth()->user()?->streamer?->id ?? 0;

        return $table
            ->query(
                StreamerLogEntry::query()
                    ->with('show')
                    ->where('streamer_id', $streamerId)
                    ->where('status', 'pending')
                    ->orderByDesc('created_at')
            )
            ->columns([
                TextColumn::make('show.show_date')
                    ->label('Date')
                    ->date('M j, Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('show.title')
                    ->label('Show')
                    ->limit(40)
                    ->placeholder('Untitled show')
                    ->weight('medium'),
                TextColumn::make('status')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn () => 'Needs your items'),
            ])
            ->recordActions([
                Action::make('add_items')
                    ->label('Add items')
                    ->icon('heroicon-o-plus-circle')
                    ->button()
                    ->url(fn (StreamerLogEntry $record) => StreamerLogResource::getUrl('edit', ['record' => $record])),
            ])
            ->recordUrl(fn (StreamerLogEntry $record) => StreamerLogResource::getUrl('edit', ['record' => $record]))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateIcon('heroicon-o-check-circle')
            ->emptyStateHeading('Nothing to review')
            ->emptyStateDescription("You're all caught up — no shows are waiting on your items.");
    }
}
