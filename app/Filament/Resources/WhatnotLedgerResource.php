<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasAdminNavVisibility;
use App\Filament\Resources\WhatnotLedgerResource\Pages;
use App\Models\WhatnotLedgerEntry;
use App\Support\StatusColor;
use App\Support\AdminModules;
use App\Support\NavVisibility;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;

class WhatnotLedgerResource extends Resource
{
    use HasAdminNavVisibility;

    protected static ?string $model = WhatnotLedgerEntry::class;

    protected static ?string $navigationLabel = 'Ledger';

    protected static ?string $modelLabel = 'ledger entry';

    protected static ?string $pluralModelLabel = 'ledger';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-document-currency-dollar';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Reports';
    }

    public static function getNavigationSort(): ?int
    {
        return 35;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return AdminModules::isEnabled('reporting')
            && ! NavVisibility::isHiddenForUser(static::class, $user)
            && (bool) $user?->isAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_date', 'desc')
            ->columns([
                TextColumn::make('created_date')
                    ->label('Created')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),

                TextColumn::make('amount')
                    ->money('USD')
                    ->sortable()
                    ->color(fn ($state) => (float) $state < 0 ? 'danger' : 'success')
                    ->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()->money('USD')->label('Net')),

                TextColumn::make('transaction_type')
                    ->label('Type')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => StatusColor::for($state)),

                TextColumn::make('whatnot_order_id')
                    ->label('Order ID')
                    ->searchable()
                    ->placeholder('—')
                    ->url(fn (WhatnotLedgerEntry $r) => $r->order_hash
                        ? "https://www.whatnot.com/dashboard/orders/{$r->order_hash}" : null, true),

                TextColumn::make('message')
                    ->wrap()
                    ->limit(80)
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('channel.name')
                    ->label('Channel')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('completed_date')
                    ->label('Completed')
                    ->dateTime('M j, Y g:i A')
                    ->toggleable()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('transaction_type')
                    ->label('Type')
                    ->options(fn () => WhatnotLedgerEntry::query()
                        ->whereNotNull('transaction_type')
                        ->distinct()
                        ->pluck('transaction_type', 'transaction_type')
                        ->toArray()),
                SelectFilter::make('whatnot_channel_id')
                    ->label('Channel')
                    ->relationship('channel', 'name'),
                SelectFilter::make('status')
                    ->options(fn () => WhatnotLedgerEntry::query()
                        ->whereNotNull('status')
                        ->distinct()
                        ->pluck('status', 'status')
                        ->toArray()),
                Filter::make('created_date')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_date', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_date', '<=', $d))),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => auth()->user()?->isOwner()),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('channel');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWhatnotLedgerEntries::route('/'),
        ];
    }
}
