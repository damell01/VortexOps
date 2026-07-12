<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Concerns\HasAdminNavVisibility;
use App\Filament\Resources\StreamerLoanResource\Pages;
use App\Models\StreamerLoan;
use App\Support\AdminModules;
use Filament\Actions\EditAction;
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

class StreamerLoanResource extends Resource
{
    use HasModuleAccess, HasAdminNavVisibility;

    protected static string $moduleSlug  = 'operations';
    protected static string $featureSlug = 'streamer_loans';
    protected static ?string $model = StreamerLoan::class;

    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-banknotes'; }
    public static function getNavigationGroup(): string|\UnitEnum|null  { return AdminModules::navigationGroupFor('operations'); }
    public static function getNavigationSort(): ?int                     { return 3; }
    public static function getModelLabel(): string                       { return 'Streamer Loan'; }
    public static function getPluralModelLabel(): string                 { return 'Streamer Loans'; }

    public static function getNavigationBadge(): ?string
    {
        $count = StreamerLoan::where('status', 'active')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string { return 'warning'; }

    public static function canCreate(): bool         { return auth()->user()?->isAdmin() ?? false; }
    public static function canEdit($r): bool         { return auth()->user()?->isAdmin() ?? false; }
    public static function canDelete($r): bool       { return auth()->user()?->isAdmin() ?? false; }
    public static function canDeleteAny(): bool      { return auth()->user()?->isAdmin() ?? false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                Select::make('streamer_id')
                    ->label('Streamer')
                    ->relationship('streamer', 'name')
                    ->searchable()
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

                TextInput::make('weekly_repayment')
                    ->label('Weekly Repayment')
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
                    ->helperText('PayoutService will deduct the weekly amount from each generated payout.'),

                Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                    ->label('Weekly')
                    ->money('USD')
                    ->placeholder('—'),

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
                ViewAction::make()->iconButton(),
                EditAction::make()->iconButton(),
            ]);
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
