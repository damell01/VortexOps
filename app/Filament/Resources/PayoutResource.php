<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\PayoutResource\Pages;
use App\Models\Payout;
use App\Models\Streamer;
use App\Support\AdminModules;
use App\Support\StatusColor;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
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
use App\Support\NavVisibility;

class PayoutResource extends Resource
{
    use HasModuleAccess {
        HasModuleAccess::shouldRegisterNavigation as private moduleShouldRegisterNavigation;
    }

    protected static string $moduleSlug  = 'payouts';

    protected static ?string $model = Payout::class;

    // Streamers can access their own payouts; row scoping handles filtering
    protected static function passesModuleAccessCheck(): bool { return true; }

    // Admins now use the "Payouts" nav item under WeeklyPayoutBatchResource
    // (week → streamer → shows) instead of this flat list — hide this one
    // from their nav so there's only one "Payouts" entry, but keep the
    // module-toggle/per-role checks HasModuleAccess already provides (this
    // method would otherwise fully shadow the trait's, silently dropping
    // that gating for everyone). Streamers still need this: it's their only
    // nav path to their own payout history (row-scoped above), since the
    // batch view is admin-only. Direct links here (e.g. PendingPayoutsWidget)
    // keep working regardless — this only hides the sidebar entry, not the
    // resource itself.
    public static function shouldRegisterNavigation(): bool
    {
        // A role granted this page on Roles & Permissions gets its link too;
        // access without a way to reach it is only half a grant.
        if (\App\Support\RoleAccess::grants(static::class)) {
            return true;
        }

        // Nav visibility is configured per role in Settings; without this
        // check an override here silently ignored that setting and the link
        // stayed in the sidebar regardless.
        if (NavVisibility::isHiddenForUser(static::class, auth()->user())) {
            return false;
        }

        if (! static::moduleShouldRegisterNavigation()) {
            return false;
        }

        return ! (auth()->user()?->isAdmin() ?? false);
    }

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

    /**
     * Nothing references payouts.id via FK, so deleting one is never
     * destructive to other tables — but once approved/paid it represents a
     * real financial decision that shouldn't quietly disappear. Only a
     * still-draft payout (not yet reviewed) is fair game.
     */
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return (auth()->user()?->isAdmin() ?? false) && $record->status === 'draft';
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
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
        // Lowercase keys are slots in the global-search override, not labels:
        // subtitle / status / tone / figure. See that view for the contract.
        return array_filter([
            'subtitle' => $record->show?->title,
            'status'   => Payout::statusLabels()[$record->status] ?? $record->status,
            'tone'     => match ($record->status) {
                'paid'     => 'success',
                'approved' => 'info',
                'draft'    => 'warning',
                default    => 'neutral',
            },
            'figure' => '$' . number_format((float) $record->calculated_payout, 2),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payout Summary')
                ->description('Overview of the show and streamer for this payout.')
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
                ->description('Breakdown of how this payout was calculated — revenue, tips, deductions, and final amount.')
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
            ->emptyStateHeading('No payouts yet')
            ->emptyStateDescription('Payouts are generated when shows are reconciled.')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->extraAttributes(['data-sticky-header' => 'true'])
            ->columns([
                // Streamer leads: a payouts list is read by person, and the
                // phone card takes its heading from the first name-ish column
                // — with the date first, "Show Date" matched on "show" and
                // became the card title.
                TextColumn::make('streamer.name')
                    ->label('Streamer')
                    ->sortable()
                    ->searchable()
                    ->weight('semibold')
                    // The show rides along as a second line rather than
                    // spending a column of its own.
                    ->description(fn (Payout $record) => $record->show?->title)
                    ->extraCellAttributes(['class' => 'vx-col-title'])
                    ->extraHeaderAttributes(['class' => 'vx-col-title']),

                TextColumn::make('show.show_date')
                    ->label('Show Date')
                    ->date('M j, Y')
                    ->sortable()
                    ->extraCellAttributes(['class' => 'vx-col-tight'])
                    ->extraHeaderAttributes(['class' => 'vx-col-tight']),

                TextColumn::make('calculated_payout')
                    // "Payout Amount", not "Payout": the card layout promotes a
                    // column into the stat row by matching its label, and
                    // "amount" is the term that gets it there.
                    ->label('Payout Amount')
                    ->money('USD')
                    ->weight('bold')
                    ->sortable()
                    ->summarize(Sum::make()->money('USD')->label('Total Payouts')),

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
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('gross_show_revenue')
                    ->label('Gross Revenue')
                    ->money('USD')
                    ->toggleable(isToggledHiddenByDefault: true)
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
                    ->money('USD')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('batch.week_start')
                    ->label('Pay Week')
                    ->formatStateUsing(fn ($state): string => $state ? $state->format('M j') : 'Unbatched')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->formatStateUsing(fn ($state) => view('components.status-badge', [
                        'status' => $state,
                        'label' => Payout::statusLabels()[$state] ?? ucfirst(str_replace('_', ' ', $state)),
                    ])->render())
                    ->html(),

                TextColumn::make('next_action')
                    ->label('Next Action')
                    ->state(fn (Payout $record): string => match ($record->status) {
                        'draft' => 'Review & approve',
                        'approved' => 'Mark as paid',
                        'paid' => 'Done',
                        default => 'Review',
                    })
                    ->badge()
                    ->color(fn (Payout $record): string => match ($record->status) {
                        'draft' => 'warning',
                        'approved' => 'info',
                        'paid' => 'success',
                        default => 'gray',
                    })
                    ->visible(fn () => auth()->user()?->isAdmin()),

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
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (Payout $record) => static::canDelete($record))
                    ->tooltip(fn (Payout $record) => static::canDelete($record) ? null : 'Only a draft payout can be deleted — approve/paid records are kept.'),
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
                    BulkAction::make('delete_drafts')
                        ->label('Delete Drafts')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('Only draft payouts are deleted; approved or paid ones are skipped so nothing already committed is lost.')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $deletable = $records->filter(fn (Payout $record) => static::canDelete($record));
                            $blocked   = $records->count() - $deletable->count();

                            $deletable->each->delete();

                            Notification::make()
                                ->title($deletable->count() . ' draft payout(s) deleted')
                                ->body($blocked > 0 ? "{$blocked} skipped — not a draft." : null)
                                ->success()
                                ->send();
                        })
                        ->visible(fn () => auth()->user()?->isAdmin())
                        ->deselectRecordsAfterCompletion(),
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
