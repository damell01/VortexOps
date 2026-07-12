<?php

namespace App\Filament\Resources\WeeklyPayoutBatchResource\RelationManagers;

use App\Models\Payout;
use App\Models\Streamer;
use App\Support\StatusColor;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PayoutsRelationManager extends RelationManager
{
    protected static string $relationship = 'payouts';

    protected static ?string $title = 'Streamer Payouts';

    public function form(Schema $schema): Schema
    {
        $batchStatus = $this->getOwnerRecord()->status ?? 'draft';
        $isLocked    = $batchStatus !== 'draft';

        return $schema->components([
            Section::make()->columns(2)->schema([
                Select::make('streamer_id')
                    ->label('Streamer')
                    ->relationship('streamer', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabled($isLocked),

                Select::make('payout_type')
                    ->label('Payout Type')
                    ->options(Streamer::payoutTypeLabels())
                    ->required()
                    ->disabled($isLocked),

                TextInput::make('calculated_payout')
                    ->label('Payout Amount ($)')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->disabled($isLocked),

                TextInput::make('routing_bank_label')
                    ->label('ADP Bank Label')
                    ->maxLength(100)
                    ->placeholder('e.g. Checking, Savings')
                    ->disabled($isLocked),

                TextInput::make('gross_show_revenue')
                    ->label('Gross Revenue ($)')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->disabled($isLocked),

                TextInput::make('tips_included')
                    ->label('Tips ($)')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->disabled($isLocked),

                TextInput::make('owner_fee_deducted')
                    ->label('Owner Fee Deducted ($)')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->disabled($isLocked),

                TextInput::make('loan_repayment_deducted')
                    ->label('Loan Repayment ($)')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->disabled($isLocked),

                Textarea::make('calculation_notes')
                    ->label('Notes / How Calculated')
                    ->rows(2)
                    ->columnSpanFull()
                    ->disabled($isLocked),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        $batch = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('id')
            ->striped()
            ->columns([
                TextColumn::make('streamer.name')
                    ->label('Streamer')
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('show.title')
                    ->label('Show')
                    ->placeholder('Manual entry')
                    ->limit(30),

                TextColumn::make('payout_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Streamer::payoutTypeLabels()[$state] ?? $state)
                    ->color('gray'),

                TextColumn::make('gross_show_revenue')
                    ->label('Gross Rev')
                    ->money('USD'),

                TextColumn::make('tips_included')
                    ->label('Tips')
                    ->money('USD')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('owner_fee_deducted')
                    ->label('Owner Fee')
                    ->money('USD')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('loan_repayment_deducted')
                    ->label('Loan Rep.')
                    ->money('USD')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('calculated_payout')
                    ->label('Payout')
                    ->money('USD')
                    ->weight('bold')
                    ->color(fn ($state) => (float) $state > 0 ? 'success' : 'gray')
                    ->summarize(Sum::make()->money('USD')->label('Total')),

                TextColumn::make('routing_bank_label')
                    ->label('ADP Bank')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Payout::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => StatusColor::for($state)),

                TextColumn::make('calculation_notes')
                    ->label('Notes')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Streamer')
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn () => $batch->status === 'draft')
                    ->mutateFormDataUsing(fn (array $data): array => array_merge($data, [
                        'status'         => 'draft',
                        'gross_show_revenue'  => $data['gross_show_revenue']  ?? 0,
                        'owner_fee_deducted'  => $data['owner_fee_deducted']  ?? 0,
                        'tips_included'       => $data['tips_included']       ?? 0,
                        'loan_repayment_deducted' => $data['loan_repayment_deducted'] ?? 0,
                    ]))
                    ->after(fn () => $batch->recalculateTotal()),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn ($record) => $batch->status === 'draft')
                    ->after(fn () => $batch->recalculateTotal()),

                DeleteAction::make()
                    ->visible(fn ($record) => $batch->status === 'draft')
                    ->after(fn () => $batch->recalculateTotal()),
            ])
            ->defaultSort('streamer.name');
    }
}
