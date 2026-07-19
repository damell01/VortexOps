<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Concerns\HasAdminNavVisibility;
use App\Filament\Resources\PayoutResource\Pages;
use App\Models\Payout;
use App\Models\Streamer;
use App\Support\AdminModules;
use App\Support\StatusColor;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\QueryBuilder\Constraints\DateConstraint;
use Filament\QueryBuilder\Constraints\NumberConstraint;
use Filament\QueryBuilder\Constraints\SelectConstraint;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class PayoutResource extends Resource
{
    use HasModuleAccess, HasAdminNavVisibility;

    protected static string $moduleSlug  = 'payouts';

    protected static ?string $model = Payout::class;

    // Streamers can access their own payouts; row scoping handles filtering
    protected static function passesModuleAccessCheck(): bool { return true; }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-banknotes';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('payouts');
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['streamer.name', 'show.title'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return 'Payout — ' . ($record->streamer?->name ?? 'Unknown');
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Show'   => $record->show?->title ?? '—',
            'Amount' => '$' . number_format((float) $record->calculated_payout, 2),
            'Status' => Payout::statusLabels()[$record->status] ?? $record->status,
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payout Summary')
                ->columns(2)
                ->columnSpanFull()
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
                ->columnSpanFull()
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
        $query = parent::getEloquentQuery()->with(['show', 'streamer', 'batch'])->inChannelContext();

        $user = auth()->user();
        if ($user && $user->isStreamer() && ! $user->isAdmin()) {
            // Always apply the filter — null means no linked profile → return nothing
            $query->where('streamer_id', $user->streamer?->id ?? 0);
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
                    ->money('USD')
                    ->summarize(Sum::make()->money('USD')->label('Total Gross')),

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
                    ->weight('bold')
                    ->summarize(Sum::make()->money('USD')->label('Total Payouts')),

                TextColumn::make('batch.week_start')
                    ->label('Pay Week')
                    ->formatStateUsing(fn ($state): string => $state ? $state->format('M j') : 'Unbatched'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Payout::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => StatusColor::for($state)),

                TextColumn::make('calculation_notes')
                    ->label('How Calculated')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->striped()
            ->persistFiltersInSession()
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->deferLoading()
            ->defaultSort('created_at', 'desc')
            ->groups([
                Group::make('streamer.name')
                    ->label('Streamer')
                    ->collapsible(),
                Group::make('batch.week_start')
                    ->label('Pay Week')
                    ->collapsible()
                    ->getTitleFromRecordUsing(fn ($record) => $record->batch?->week_start?->format('M j, Y') ?? 'Unbatched'),
                Group::make('status')
                    ->label('Status')
                    ->collapsible()
                    ->getTitleFromRecordUsing(fn ($record) => Payout::statusLabels()[$record->status] ?? $record->status),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(Payout::statusLabels())
                    ->multiple(),
                SelectFilter::make('streamer_id')
                    ->label('Streamer')
                    ->options(fn () => Cache::remember('filter:streamers', 300, fn () => Streamer::pluck('name', 'id')->toArray()))
                    ->multiple()
                    ->visible(fn () => auth()->user()?->isAdmin()),
                QueryBuilder::make()
                    ->label('Advanced Filters')
                    ->constraintPickerColumns(2)
                    ->constraints([
                        DateConstraint::make('created_at')->label('Date Created'),
                        NumberConstraint::make('calculated_payout')->label('Payout Amount ($)'),
                        NumberConstraint::make('gross_show_revenue')->label('Gross Revenue ($)'),
                        SelectConstraint::make('status')
                            ->options(Payout::statusLabels())
                            ->multiple(),
                        SelectConstraint::make('payout_type')
                            ->label('Payout Type')
                            ->options(Streamer::payoutTypeLabels()),
                    ]),
            ])
            ->actions([
                ViewAction::make()->iconButton(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $count = 0;
                            foreach ($records as $payout) {
                                if ($payout->status === 'draft') {
                                    $payout->update(['status' => 'approved']);
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title("{$count} payout(s) approved")
                                ->success()
                                ->send();
                        })
                        ->visible(fn () => auth()->user()?->isAdmin()),
                    BulkAction::make('mark_paid')
                        ->label('Mark Paid')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Mark selected payouts as paid')
                        ->modalDescription('Only approved payouts are marked paid; drafts are skipped so nothing unreviewed slips through.')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $count = 0;
                            $skipped = 0;
                            foreach ($records as $payout) {
                                if ($payout->status === 'approved') {
                                    $payout->update(['status' => 'paid']);
                                    $count++;
                                } elseif ($payout->status !== 'paid') {
                                    $skipped++;
                                }
                            }
                            Notification::make()
                                ->title("{$count} payout(s) marked paid" . ($skipped > 0 ? " · {$skipped} skipped (not approved)" : ''))
                                ->success()
                                ->send();
                        })
                        ->visible(fn () => auth()->user()?->isAdmin()),
                    ExportBulkAction::make(),
                ]),
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
