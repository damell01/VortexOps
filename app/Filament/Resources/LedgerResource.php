<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LedgerResource\Pages;
use App\Models\LedgerEntry;
use App\Models\Streamer;
use App\Support\AdminModules;
use App\Support\NavVisibility;
use App\Services\LedgerService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Concerns\HasAdminNavVisibility;

class LedgerResource extends Resource
{
    use HasAdminNavVisibility {
        HasAdminNavVisibility::shouldRegisterNavigation as navVisibilityShouldRegisterNavigation;
    }

    protected static ?string $model = LedgerEntry::class;

    protected static ?string $navigationLabel = 'Finance Ledger';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-banknotes';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Reports';
    }

    public static function getNavigationSort(): ?int
    {
        return 30;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return AdminModules::isEnabled('reporting')
            && ! NavVisibility::isHiddenForUser(static::class, $user)
            && static::isVisibleToRole();
    }

    // Retired from the nav: the Whatnot-fed "Ledger" (WhatnotLedgerResource) is now
    // the single ledger view. The internal accounting entries remain in the DB and
    // are still reachable via LedgerService; this resource is just hidden.
    /**
     * Was hardcoded to false, hiding the ledger from every account regardless
     * of the navigation-visibility settings. Gated on role, then deferred to
     * HasAdminNavVisibility so those settings still apply.
     */
    public static function shouldRegisterNavigation(): bool
    {
        // Gate on canAccess too: it also checks the reporting module, and
        // without it the entry showed in the menu while the page 403'd.
        if (! static::canAccess()) {
            return false;
        }

        return static::navVisibilityShouldRegisterNavigation();
    }

    /** Financial history, so admins and owners only. */
    protected static function isVisibleToRole(): bool
    {
        $user = auth()->user();

        return ($user?->isOwner() ?? false) || ($user?->isAdmin() ?? false);
    }

    public static function canCreate(): bool   { return false; }
    public static function canEdit($r): bool   { return false; }
    public static function canDelete($r): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->schema([
                Select::make('type')
                    ->options(LedgerEntry::typeLabels())
                    ->required(),
                Select::make('direction')
                    ->options(LedgerEntry::directionLabels())
                    ->required(),
                TextInput::make('amount')
                    ->numeric()
                    ->prefix('$')
                    ->required(),
                TextInput::make('description')
                    ->maxLength(500)
                    ->required(),
                DatePicker::make('transaction_date')
                    ->default(now()->toDateString())
                    ->required(),
                Select::make('streamer_id')
                    ->label('Streamer')
                    ->options(Streamer::orderBy('name')->pluck('name', 'id'))
                    ->nullable()
                    ->searchable(),
                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No ledger entries')
            ->emptyStateDescription('Financial entries appear here as shows are reconciled.')
            ->emptyStateIcon('heroicon-o-book-open')
            ->columns([
                TextColumn::make('transaction_date')
                    ->date()
                    ->sortable()
                    ->label('Date'),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => LedgerEntry::typeLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'deposit'            => 'success',
                        'payout'             => 'info',
                        'loan_repayment'     => 'warning',
                        'shipping_surcharge' => 'warning',
                        'adjustment'         => 'gray',
                        'owner_fee'          => 'purple',
                        'streamer_fee'       => 'violet',
                        'tips'               => 'teal',
                        'wholesale_payment'  => 'indigo',
                        'rebate'             => 'pink',
                        'collects_transfer'  => 'cyan',
                        default              => 'gray',
                    }),
                TextColumn::make('direction')
                    ->badge()
                    ->color(fn ($state) => $state === 'credit' ? 'success' : 'danger'),
                TextColumn::make('description')
                    ->wrap()
                    ->limit(60),
                TextColumn::make('streamer.name')
                    ->label('Streamer')
                    ->toggleable(),
                TextColumn::make('amount')
                    ->formatStateUsing(fn ($state, LedgerEntry $record) =>
                        '$' . number_format((float) $state, 2)
                    )
                    ->color(fn (LedgerEntry $record) => $record->direction === 'credit' ? 'success' : 'danger'),
                TextColumn::make('balance_after')
                    ->label('Balance')
                    ->fontFamily('mono')
                    ->formatStateUsing(fn ($state) => '$' . number_format((float) $state, 2)),
                TextColumn::make('notes')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options(LedgerEntry::typeLabels()),
                SelectFilter::make('direction')
                    ->options(LedgerEntry::directionLabels()),
                SelectFilter::make('streamer_id')
                    ->label('Streamer')
                    ->options(Streamer::orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
                Filter::make('transaction_date')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'],  fn ($q) => $q->where('transaction_date', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->where('transaction_date', '<=', $data['until']));
                    }),
            ])
            ->headerActions([
                Action::make('new_entry')
                    ->label('New Entry')
                    ->icon('heroicon-o-plus')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isOwner())
                    ->form([
                        Select::make('type')
                            ->options(LedgerEntry::typeLabels())
                            ->required(),
                        Select::make('direction')
                            ->options(LedgerEntry::directionLabels())
                            ->required(),
                        TextInput::make('amount')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        TextInput::make('description')
                            ->maxLength(500)
                            ->required(),
                        DatePicker::make('transaction_date')
                            ->default(now()->toDateString())
                            ->required(),
                        Select::make('streamer_id')
                            ->label('Streamer (optional)')
                            ->options(Streamer::orderBy('name')->pluck('name', 'id'))
                            ->nullable()
                            ->searchable(),
                        Textarea::make('notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data) {
                        app(LedgerService::class)->record($data);
                        Notification::make()->title('Ledger entry created')->success()->send();
                    }),
            ])
            ->actions([
                Action::make('void')
                    ->label('Void')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (LedgerEntry $record) {
                        $offsetDirection = $record->direction === 'credit' ? 'debit' : 'credit';
                        app(LedgerService::class)->record([
                            'type'             => $record->type,
                            'direction'        => $offsetDirection,
                            'amount'           => (float) $record->amount,
                            'description'      => 'Voided: ' . $record->description,
                            'reference_type'   => LedgerEntry::class,
                            'reference_id'     => $record->id,
                            'streamer_id'      => $record->streamer_id,
                            'show_id'          => $record->show_id,
                            'transaction_date' => now()->toDateString(),
                        ]);
                        Notification::make()->title('Entry voided')->success()->send();
                    }),
            ])
            ->striped()
            ->deferLoading()
            ->persistFiltersInSession()
            ->paginationPageOptions([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('streamer');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLedgerEntries::route('/'),
        ];
    }
}
