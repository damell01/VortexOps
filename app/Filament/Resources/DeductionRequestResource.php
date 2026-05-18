<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeductionRequestResource\Pages;
use App\Models\DeductionRequest;
use App\Services\ReconciliationService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class DeductionRequestResource extends Resource
{
    protected static ?string $model = DeductionRequest::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-clipboard-document-check';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Stream Tracking';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getModelLabel(): string
    {
        return 'Deduction Request';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Deduction Requests';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = DeductionRequest::where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function canCreate(): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
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
                    ->default('—')
                    ->searchable(),

                TextColumn::make('inventoryItem.name')
                    ->label('Item')
                    ->searchable(),

                TextColumn::make('inventoryItem.sku')
                    ->label('SKU')
                    ->default('—'),

                TextColumn::make('location.name')
                    ->label('Location'),

                TextColumn::make('quantity')
                    ->numeric(2),

                TextColumn::make('show.streamers.name')
                    ->label('Streamer(s)')
                    ->badge()
                    ->separator(', '),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => DeductionRequest::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'pending'  => 'warning',
                        'approved' => 'info',
                        'rejected' => 'danger',
                        'executed' => 'success',
                        default    => 'gray',
                    }),

                TextColumn::make('reviewedBy.name')
                    ->label('Reviewed By')
                    ->default('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(DeductionRequest::statusLabels())
                    ->default('pending'),
            ])
            ->actions([
                Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (DeductionRequest $r) => $r->status === 'pending' && auth()->user()?->isAdmin())
                    ->requiresConfirmation()
                    ->action(function (DeductionRequest $record) {
                        app(ReconciliationService::class)->approve($record);
                        Notification::make()->title('Deduction approved.')->success()->send();
                    }),

                Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (DeductionRequest $r) => $r->status === 'pending' && auth()->user()?->isAdmin())
                    ->form([
                        Textarea::make('rejection_reason')
                            ->label('Reason for rejection')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (DeductionRequest $record, array $data) {
                        app(ReconciliationService::class)->reject($record, $data['rejection_reason']);
                        Notification::make()->title('Deduction rejected.')->warning()->send();
                    }),

                ViewAction::make()->iconButton(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_approve')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn () => auth()->user()?->isAdmin())
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $svc   = app(ReconciliationService::class);
                            $count = 0;
                            foreach ($records->where('status', 'pending') as $r) {
                                $svc->approve($r);
                                $count++;
                            }
                            Notification::make()->title("{$count} deduction(s) approved.")->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeductionRequests::route('/'),
            'view'  => Pages\ViewDeductionRequest::route('/{record}'),
        ];
    }
}
