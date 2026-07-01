<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PayoutResource;
use App\Models\Payout;
use App\Support\AdminModules;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PendingPayoutsWidget extends BaseWidget
{
    protected static ?int $sort = 4;
    protected static ?string $heading = 'Pending Payouts';
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return AdminModules::isEnabled('payouts');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Payout::query()
                    ->with(['streamer', 'show'])
                    ->where('status', 'draft')
                    ->orderBy('calculated_payout', 'desc')
            )
            ->columns([
                TextColumn::make('streamer.name')
                    ->label('Streamer')
                    ->searchable(),
                TextColumn::make('show.title')
                    ->label('Show')
                    ->limit(35)
                    ->placeholder('—'),
                TextColumn::make('show.show_date')
                    ->label('Show Date')
                    ->date('M j, Y')
                    ->placeholder('—'),
                TextColumn::make('payout_type')
                    ->badge()
                    ->color('info'),
                TextColumn::make('calculated_payout')
                    ->label('Amount')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('routing_bank_label')
                    ->label('Bank')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->emptyStateHeading('No pending payouts')
            ->emptyStateDescription('All draft payouts have been reviewed.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->recordUrl(fn ($record) => PayoutResource::getUrl('view', ['record' => $record]))
            ->paginated(10);
    }
}
