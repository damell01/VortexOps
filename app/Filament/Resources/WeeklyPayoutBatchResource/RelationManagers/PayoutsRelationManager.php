<?php

namespace App\Filament\Resources\WeeklyPayoutBatchResource\RelationManagers;

use App\Filament\Resources\ShowResource;
use App\Models\Payout;
use App\Models\Streamer;
use App\Support\StatusColor;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class PayoutsRelationManager extends RelationManager
{
    protected static string $relationship = 'payouts';

    protected static ?string $title = 'Weekly Earnings Breakdown';

    public function form(Schema $schema): Schema
    {
        $batch = $this->getOwnerRecord();
        $isLocked = ($batch?->status ?? 'draft') !== 'draft';

        return $schema->components([
            Section::make()->columns(2)->schema([
                Select::make('streamer_id')
                    ->label('Team Member')
                    ->relationship('streamer', 'name')
                    ->searchable()->preload()->required()->disabled($isLocked),
                Select::make('payout_type')
                    ->label('Payout Type')
                    ->options(Streamer::payoutTypeLabels())
                    ->required()->disabled($isLocked),
                TextInput::make('calculated_payout')
                    ->label('Earnings ($)')->numeric()->minValue(0)->required()->disabled($isLocked),
                TextInput::make('routing_bank_label')
                    ->label('ADP Bank Label')->maxLength(100)->disabled($isLocked),
                TextInput::make('gross_show_revenue')->label('Gross Revenue ($)')->numeric()->minValue(0)->default(0)->disabled($isLocked),
                TextInput::make('product_cost')->label('Product Cost ($)')->numeric()->minValue(0)->disabled($isLocked),
                TextInput::make('hours_worked')->label('Hours')->numeric()->minValue(0)->disabled($isLocked),
                TextInput::make('shipments_count')->label('Shipments')->numeric()->minValue(0)->disabled($isLocked),
                TextInput::make('pwe_count')->label('PWE Count')->numeric()->minValue(0)->disabled($isLocked),
                TextInput::make('label_count')->label('Label Count')->numeric()->minValue(0)->disabled($isLocked),
                TextInput::make('tips_included')->label('Tips ($)')->numeric()->minValue(0)->default(0)->disabled($isLocked),
                Textarea::make('calculation_notes')->label('Calculation')->rows(3)->columnSpanFull()->disabled($isLocked),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        $batch = $this->getOwnerRecord();
        if (! $batch) {
            return $table->recordTitleAttribute('id');
        }

        return $table
            ->recordTitleAttribute('id')
            ->striped()
            ->columns([
                TextColumn::make('streamer.name')->label('Team Member')->sortable()->weight('semibold'),
                TextColumn::make('streamer.member_type')
                    ->label('Team')->badge()
                    ->formatStateUsing(fn ($state) => Streamer::memberTypeLabels()[$state ?? 'streamer'] ?? ucfirst((string) $state))
                    ->color('gray'),
                TextColumn::make('show.title')
                    ->label('Source / Show')->badge()->color('gray')->placeholder('Manual / weekly activity')->limit(30)
                    ->url(fn (Payout $record) => $record->show_id ? ShowResource::getUrl('view', ['record' => $record->show_id]) : null)
                    ->openUrlInNewTab(),
                TextColumn::make('payout_type')->label('Type')->badge()
                    ->formatStateUsing(fn ($state) => Streamer::payoutTypeLabels()[$state] ?? $state)->color('gray'),
                TextColumn::make('gross_show_revenue')->label('Gross Rev')->money('USD'),
                TextColumn::make('product_cost')->label('Product Cost')->money('USD')->placeholder('—'),
                TextColumn::make('hours_worked')->label('Hours')->numeric(decimalPlaces: 2)->placeholder('—'),
                TextColumn::make('shipments_count')->label('Shipments')->numeric()->placeholder('—'),
                TextColumn::make('pwe_count')->label('PWE')->numeric()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('label_count')->label('Labels')->numeric()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('calculated_payout')->label('Earnings')->money('USD')->weight('bold')
                    ->color(fn ($state) => (float) $state > 0 ? 'success' : 'gray')
                    ->summarize(Sum::make()->money('USD')->label('Weekly Total')),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => Payout::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => StatusColor::for($state)),
                TextColumn::make('calculation_notes')->label('Calculation')->limit(55)->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Team Member / Adjustment')
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn () => $batch->status === 'draft')
                    ->mutateFormDataUsing(fn (array $data): array => array_merge($data, [
                        'status' => 'draft',
                        'gross_show_revenue' => $data['gross_show_revenue'] ?? 0,
                        'owner_fee_deducted' => $data['owner_fee_deducted'] ?? 0,
                        'tips_included' => $data['tips_included'] ?? 0,
                        'loan_repayment_deducted' => $data['loan_repayment_deducted'] ?? 0,
                    ]))
                    ->after(fn () => $batch->recalculateTotal()),
            ])
            ->actions([
                EditAction::make()->visible(fn () => $batch->status === 'draft')->after(fn () => $batch->recalculateTotal()),
                DeleteAction::make()->visible(fn () => $batch->status === 'draft')->after(fn () => $batch->recalculateTotal()),
            ])
            ->groups([
                Group::make('streamer.name')
                    ->label('Team Member')
                    ->getDescriptionFromRecordUsing(function (Payout $record) use ($batch): string {
                        $rows = Payout::where('weekly_payout_batch_id', $batch->id)->where('streamer_id', $record->streamer_id);
                        $total = (clone $rows)->sum('calculated_payout');
                        $count = (clone $rows)->count();
                        return $count . ' earning source' . ($count === 1 ? '' : 's') . ' — $' . number_format((float) $total, 2) . ' weekly total';
                    })
                    ->collapsible(),
            ])
            ->defaultGroup('streamer.name')
            ->collapsedGroupsByDefault()
            ->groupsOnly()
            ->defaultSort('streamer.name');
    }
}
