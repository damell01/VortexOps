<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\StreamerResource;
use App\Models\Streamer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class StreamerBalanceWidget extends BaseWidget
{
    protected static ?int $sort = 6;
    protected static ?string $heading = 'Streamer Earnings Balance';
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Streamer::query()
                    ->where('status', 'active')
                    ->orderByRaw('(total_earnings_due - total_earnings_paid) DESC')
                    ->orderBy('name')
            )
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->url(fn ($record) => StreamerResource::getUrl('view', ['record' => $record])),

                TextColumn::make('payout_type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Streamer::payoutTypeLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'profit_share' => 'success',
                        'hourly'       => 'warning',
                        'pwe_labels'   => 'info',
                        'hybrid'       => 'primary',
                        default        => 'gray',
                    }),

                TextColumn::make('total_earnings_due')
                    ->label('Total Due')
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('total_earnings_paid')
                    ->label('Total Paid')
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('outstanding_balance')
                    ->label('Outstanding')
                    ->state(fn ($record) => $record->outstandingBalance())
                    ->money('USD')
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success')
                    ->weight('bold'),
            ])
            ->deferLoading()
            ->recordUrl(fn ($record) => StreamerResource::getUrl('view', ['record' => $record]))
            ->paginated(false);
    }
}
