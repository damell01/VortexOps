<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShippingSurchargeResource\Pages;
use App\Models\ShippingSurcharge;
use App\Models\Streamer;
use App\Support\AdminModules;
use App\Support\NavVisibility;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Concerns\HasAdminNavVisibility;

class ShippingSurchargeResource extends Resource
{
    use HasAdminNavVisibility;

    protected static ?string $model = ShippingSurcharge::class;

    protected static ?string $navigationLabel = 'Shipping Surcharges';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-truck';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Finance';
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

    public static function canAccess(): bool
    {
        // An explicit grant on Roles & Permissions is the answer; the rules
        // below are the fallback for roles that have no explicit list.
        if (\App\Support\RoleAccess::grants(static::class)) {
            return true;
        }

        $user = auth()->user();

        return AdminModules::isEnabled('payouts')
            && ! NavVisibility::isHiddenForUser(static::class, $user)
            && (bool) $user?->isAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No surcharges')
            ->emptyStateDescription('Add shipping surcharges to pass carrier fees through to payouts.')
            ->emptyStateIcon('heroicon-o-truck')
            ->columns([
                TextColumn::make('show.show_date')
                    ->label('Show Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('show.title')
                    ->label('Show')
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('streamer.name')
                    ->label('Streamer')
                    ->searchable(),
                TextColumn::make('package_count')
                    ->label('Packages')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rate_per_package')
                    ->label('Rate/Pkg')
                    ->money('USD'),
                TextColumn::make('threshold_amount')
                    ->label('Threshold')
                    ->money('USD'),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('USD')
                    ->sortable(),
                IconColumn::make('deducted_from_payout')
                    ->label('Deducted')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('warning'),
                TextColumn::make('next_action')
                    ->label('Next Action')
                    ->state(fn (ShippingSurcharge $record): string => $record->deducted_from_payout
                        ? 'Complete ✓'
                        : 'Apply to payout')
                    ->badge()
                    ->color(fn (ShippingSurcharge $record): string => $record->deducted_from_payout
                        ? 'success'
                        : 'info'),
            ])
            ->filters([
                SelectFilter::make('streamer_id')
                    ->label('Streamer')
                    ->options(Streamer::orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
                Filter::make('show_date')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'],  fn ($q) => $q->whereHas('show', fn ($s) => $s->where('show_date', '>=', $data['from'])))
                            ->when($data['until'], fn ($q) => $q->whereHas('show', fn ($s) => $s->where('show_date', '<=', $data['until'])));
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->deferLoading()
            ->persistFiltersInSession();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['show', 'streamer']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShippingSurcharges::route('/'),
        ];
    }
}
