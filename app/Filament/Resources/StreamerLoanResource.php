<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\StreamerLoanResource\Pages;
use App\Models\StreamerLoan;
use App\Support\AdminModules;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

class StreamerLoanResource extends Resource
{
    use HasModuleAccess { canAccess as protected moduleCanAccess; }

    protected static string $moduleSlug  = 'payouts';
    protected static ?string $model = StreamerLoan::class;

    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-banknotes'; }
    public static function getNavigationGroup(): string|\UnitEnum|null  { return 'Payroll'; }
    public static function getNavigationSort(): ?int                     { return 3; }
    public static function getModelLabel(): string                       { return 'Streamer Loan'; }
    public static function getPluralModelLabel(): string                 { return 'Streamer Loans'; }

    public static function getNavigationBadge(): ?string
    {
        $count = Cache::remember('nav_badge:streamer_loans_active', 60, fn () => StreamerLoan::where('status', 'active')->count());
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string { return 'warning'; }

    public static function canAccess(): bool
    {
        return (auth()->user()?->isAdmin() ?? false) && static::moduleCanAccess();
    }

    public static function canCreate(): bool         { return auth()->user()?->isAdmin() ?? false; }
    public static function canEdit($r): bool         { return auth()->user()?->isAdmin() ?? false; }
    public static function canDelete($r): bool       { return auth()->user()?->isAdmin() ?? false; }
    public static function canDeleteAny(): bool      { return auth()->user()?->isAdmin() ?? false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                Select::make('streamer_id')
                    ->label('Streamer')
                    ->options(fn (): array => \App\Models\Streamer::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->native(false)
                    ->preload()
                    ->placeholder('Search or select a streamer')
                    ->helperText('Tap to choose a streamer, or type their name to filter the list.')
                    ->required(),

                TextInput::make('label')
                    ->label('Loan Label')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Equipment advance'),

                TextInput::make('original_amount')
                    ->label('Original Amount')
                    ->numeric()
                    ->prefix('$')
                    ->required()
                    ->minValue(0),

                TextInput::make('remaining_balance')
                    ->label('Remaining Balance')
                    ->numeric()
                    ->prefix('$')
                    ->required()
                    ->minValue(0),

                Select::make('repayment_frequency')
                    ->label('Repayment Schedule')
                    ->options(['weekly' => 'Weekly', 'monthly' => 'Monthly'])
                    ->default('weekly')
                    ->required()
                    ->native(false),

                TextInput::make('weekly_repayment')
                    ->label('Repayment Amount')
                    ->numeric()
                    ->prefix('$')
                    ->nullable()
                    ->minValue(0),

                Select::make('status')
                    ->options(StreamerLoan::statusLabels())
                    ->default('active')
                    ->required(),

                Toggle::make('deduct_from_payout')
                    ->label('Auto-deduct from payout')
                    ->default(true)
                    ->helperText('Deducts the configured amount once per weekly or monthly period at pay run finalization.'),

                Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->deferLoading()
            ->emptyStateHeading('No loans')
            ->emptyStateDescription('Streamer loans and advances are tracked and repaid here.')
            ->emptyStateIcon('heroicon-o-currency-dollar')
            ->columns([
                TextColumn::make('streamer.name')
                    ->label('Streamer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('label')
                    ->label('Label')
                    ->searchable(),

                TextColumn::make('original_amount')
                    ->label('Original')
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('remaining_balance')
                    ->label('Remaining')
                    ->money('USD')
                    ->sortable()
                    ->color(fn ($state) => (float) $state > 0 ? 'warning' : 'success'),

                TextColumn::make('weekly_repayment')
                    ->label('Repayment')
                    ->money('USD')
                    ->placeholder('—'),

                TextColumn::make('repayment_frequency')
                    ->label('Schedule')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state ?: 'weekly')),

                IconColumn::make('deduct_from_payout')
                    ->label('Auto-deduct')
                    ->boolean(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => StreamerLoan::statusLabels()[$state] ?? $state)
                    // Kept inline, not routed through StatusColor: an "active" loan
                    // means money is still owed, so it reads as a warning here rather
                    // than the "success" that "active" carries elsewhere.
                    ->color(fn ($state) => match ($state) {
                        'active'   => 'warning',
                        'paid_off' => 'success',
                        default    => 'gray',
                    }),

                TextColumn::make('next_action')
                    ->label('Next Action')
                    ->state(fn (StreamerLoan $record): string => match ($record->status) {
                        'active' => 'Track repayments',
                        'paid_off' => 'Complete ✓',
                        default => 'Review',
                    })
                    ->badge()
                    ->color(fn (StreamerLoan $record): string => match ($record->status) {
                        'active' => 'warning',
                        'paid_off' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('latest_payment_date')
                    ->label('Last Payment')
                    ->state(fn (StreamerLoan $record) => $record->payments()->max('payment_date'))
                    ->date('M j, Y')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->filters([
                SelectFilter::make('status')->options(StreamerLoan::statusLabels()),
                SelectFilter::make('streamer_id')
                    ->label('Streamer')
                    ->relationship('streamer', 'name'),
            ])
            ->actions([
                Action::make('record_payment')
                    ->label('Record Payment')->icon('heroicon-o-banknotes')->color('success')
                    ->visible(fn (StreamerLoan $record) => $record->status === 'active')
                    ->form([
                        TextInput::make('amount')->label('Amount ($)')->numeric()->minValue(0.01)->required(),
                        \Filament\Forms\Components\DatePicker::make('payment_date')->label('Payment Date')->default(now())->maxDate(now())->required(),
                        Textarea::make('note')->label('Note (optional)'),
                    ])
                    ->action(function (StreamerLoan $record, array $data): void {
                        $record->recordManualPayment((float) $data['amount'], (string) $data['payment_date'], $data['note'] ?? null);
                        \Filament\Notifications\Notification::make()->title('Loan payment recorded')->success()->send();
                    }),
                ViewAction::make()->iconButton(),
                EditAction::make()->iconButton(),
                \Filament\Actions\DeleteAction::make()->iconButton(),
            ]);
    }

    /**
     * Admin-only. HasModuleAccess lets any signed-in user through by default,
     * and this table lists every streamer's advance — label, original amount,
     * weekly repayment, and outstanding balance — with no row-level scoping,
     * so without this a streamer opening /admin/streamer-loans read the whole
     * roster's borrowing. The record pages were already covered by
     * StreamerLoanPolicy; the list was not.
     */
    protected static function passesModuleAccessCheck(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with('streamer');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListStreamerLoans::route('/'),
            'create' => Pages\CreateStreamerLoan::route('/create'),
            'view'   => Pages\ViewStreamerLoan::route('/{record}'),
            'edit'   => Pages\EditStreamerLoan::route('/{record}/edit'),
        ];
    }
}
