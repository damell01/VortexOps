<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayoutResource\Pages;
use App\Models\Payout;
use App\Models\Streamer;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payout Summary')
                ->columns(2)
                ->schema([
                    Placeholder::make('show')
                        ->label('Show')
                        ->content(fn (Payout $record): string => $record->show?->title ?? '—'),
                    Placeholder::make('show_date')
                        ->label('Show Date')
                        ->content(fn (Payout $record): string => $record->show?->show_date?->format('M j, Y') ?? '—'),
                    Placeholder::make('streamer')
                        ->label('Streamer')
                        ->content(fn (Payout $record): string => $record->streamer?->name ?? '—'),
                    Placeholder::make('status')
                        ->label('Status')
                        ->content(fn (Payout $record): string => Payout::statusLabels()[$record->status] ?? $record->status),
                ]),
            Section::make('Calculation')
                ->schema([
                    Grid::make(2)->schema([
                        Placeholder::make('payout_type')
                            ->label('Payout Type')
                            ->content(fn (Payout $record): string => Streamer::payoutTypeLabels()[$record->payout_type] ?? $record->payout_type),
                        Placeholder::make('batch')
                            ->label('Pay Run')
                            ->content(fn (Payout $record): string => $record->batch?->week_start?->format('M j, Y') ?? 'Unbatched'),
                        Placeholder::make('gross_show_revenue')
                            ->label('Gross Revenue')
                            ->content(fn (Payout $record): string => '$' . number_format((float) $record->gross_show_revenue, 2)),
                        Placeholder::make('tips_included')
                            ->label('Tips Included')
                            ->content(fn (Payout $record): string => '$' . number_format((float) $record->tips_included, 2)),
                        Placeholder::make('owner_fee_deducted')
                            ->label('Owner Fee Deducted')
                            ->content(fn (Payout $record): string => '$' . number_format((float) $record->owner_fee_deducted, 2)),
                        Placeholder::make('loan_repayment_deducted')
                            ->label('Loan Repayment Deducted')
                            ->content(fn (Payout $record): string => '$' . number_format((float) $record->loan_repayment_deducted, 2)),
                        Placeholder::make('calculated_payout')
                            ->label('Final Payout')
                            ->content(fn (Payout $record): string => '$' . number_format((float) $record->calculated_payout, 2)),
                    ]),
                    Placeholder::make('calculation_notes')
                        ->label('How It Was Calculated')
                        ->content(fn (Payout $record): string => $record->calculation_notes ?: '—'),
                ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['show', 'streamer', 'batch']);

        $user = auth()->user();
        if ($user && $user->isStreamer() && ! $user->isAdmin()) {
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
                        'package' => 'info',
                        'hourly' => 'warning',
                        'flat_rate' => 'gray',
                        'custom_formula' => 'primary',
                        default => 'gray',
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
                    ->formatStateUsing(fn ($state): string => $state ? $state->format('M j') : 'Unbatched'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Payout::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'draft' => 'gray',
                        'approved' => 'info',
                        'paid' => 'success',
                        default => 'gray',
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
            'view' => Pages\ViewPayout::route('/{record}'),
        ];
    }
}
