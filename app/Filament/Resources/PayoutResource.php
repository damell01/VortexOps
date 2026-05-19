<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayoutResource\Pages;
use App\Models\Payout;
use App\Models\Streamer;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PayoutResource extends Resource
{
    protected static ?string $model = Payout::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-banknotes';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Payouts & Pay Runs';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function canCreate(): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['show', 'streamer', 'batch']);

        $user = auth()->user();
        if ($user && $user->isStreamer() && !$user->isAdmin()) {
            $streamerId = $user->streamer?->id;
            if ($streamerId) {
                $query->where('streamer_id', $streamerId);
            }
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('show.show_date')
                    ->label('Show Date')
                    ->date('M j, Y')
                    ->sortable(),

                TextColumn::make('show.title')
                    ->label('Show')
                    ->default('—'),

                TextColumn::make('streamer.name')
                    ->label('Streamer')
                    ->sortable(),

                TextColumn::make('payout_type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Streamer::payoutTypeLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'profit_share' => 'success',
                        'package'      => 'info',
                        'hourly'       => 'warning',
                        'flat_rate'    => 'gray',
                        default        => 'gray',
                    }),

                TextColumn::make('gross_show_revenue')
                    ->label('Gross Revenue')
                    ->money('USD'),

                TextColumn::make('owner_fee_deducted')
                    ->label('Owner Fee')
                    ->money('USD')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('loan_repayment_deducted')
                    ->label('Loan Repayment')
                    ->money('USD')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('tips_included')
                    ->label('Tips')
                    ->money('USD'),

                TextColumn::make('calculated_payout')
                    ->label('Payout')
                    ->money('USD')
                    ->weight('bold'),

                TextColumn::make('batch.week_start')
                    ->label('Pay Week')
                    ->date('M j')
                    ->default('Unbatched'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Payout::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'draft'    => 'gray',
                        'approved' => 'info',
                        'paid'     => 'success',
                        default    => 'gray',
                    }),

                TextColumn::make('calculation_notes')
                    ->label('How Calculated')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(Payout::statusLabels()),
                SelectFilter::make('streamer_id')
                    ->label('Streamer')
                    ->options(Streamer::pluck('name', 'id'))
                    ->visible(fn () => auth()->user()?->isAdmin()),
            ])
            ->actions([
                ViewAction::make()->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayouts::route('/'),
            'view'  => Pages\ViewPayout::route('/{record}'),
        ];
    }
}
