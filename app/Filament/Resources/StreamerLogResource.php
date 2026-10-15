<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StreamerLogResource\Pages;
use App\Filament\Resources\StreamerLogResource\RelationManagers;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Support\AdminModules;
use App\Support\NavVisibility;
use App\Support\StatusColor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Collection;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Concerns\HasModuleAccess;

class StreamerLogResource extends Resource
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'streams';

    protected static ?string $model = StreamerLogEntry::class;

    protected static ?string $navigationLabel = 'Show Report Inbox';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-inbox-stack';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Operations';
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'streamer-logs';
    }

    public static function canAccess(): bool
    {
        // An explicit grant on Roles & Permissions is the answer; the rules
        // below are the fallback for roles that have no explicit list.
        if (\App\Support\RoleAccess::grants(static::class)) {
            return true;
        }

        $user = auth()->user();
        if (! AdminModules::isEnabled('streams') || NavVisibility::isHiddenForUser(static::class, $user)) {
            return false;
        }

        // fulfillment_admin needs in: they're the ones who action the
        // "Fulfillment Reviewed" step below for PWE + Labels streamers.
        return $user?->isAdmin() || $user?->isStreamer() || $user?->isFulfillmentAdmin();
    }

    /**
     * Deliberately narrower than canAccess(): streamers can open their own log
     * but reach it from their own pages, not from a top-level sidebar entry.
     *
     * Kept as an override rather than folded into the trait's access-derived
     * rule because it is a product decision, not a leftover role check — the
     * two other resources that looked like this turned out to be contradicting
     * their own access rules. Hiding it on Roles & Permissions still works.
     */
    /** Secondary workspace screen: linked from its hub; access/permissions are unchanged. */
    /** Secondary Shows workspace screen; permissions and direct access remain unchanged. */
    public static function shouldRegisterNavigation(): bool
    {
        return (auth()->user()?->isAdmin() || auth()->user()?->isOwner() || auth()->user()?->isFulfillmentAdmin()) ?? false;
    }

    /**
     * Streamers can edit their log entries until they submit. After submission,
     * they can only edit within the allowed time window. Admins can always edit.
     */
    public static function isLockedForCurrentUser(?StreamerLogEntry $record): bool
    {
        $user = auth()->user();
        if (! $record || $user?->isAdmin() || $user?->isOwner()) {
            return false;
        }

        // Streamers can edit until submission or within the edit window
        if ($user?->isStreamer()) {
            return ! $record->canStreamerEdit();
        }

        // Fulfillment admins can edit when PWE/label review is pending
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Show')
                ->visible(fn (?StreamerLogEntry $record) => $record !== null)
                ->columnSpanFull()
                ->schema([
                    Placeholder::make('show_label')
                        ->label('')
                        ->content(fn (?StreamerLogEntry $record) => $record?->show
                            ? new \Illuminate\Support\HtmlString(
                                '<span style="font-weight:600">' . e($record->show->title ?: 'Untitled show') . '</span>'
                                . ($record->show->show_date ? ' <span style="color:#6b7280">· ' . e($record->show->show_date->format('M j, Y')) . '</span>' : '')
                            )
                            : '—'),
                ]),

            Section::make('Show Info')
                ->description('Record key metrics from your show — hours worked, shipments, and package counts.')
                ->disabled(fn (?StreamerLogEntry $record) => static::isLockedForCurrentUser($record))
                ->columnSpanFull()
                ->schema([
                Grid::make(2)->schema([
                    Toggle::make('hard_copy')
                        ->label('Hard Copy (physical log filed)')
                        ->helperText('Check if you filed a physical log sheet for this show'),
                    TextInput::make('hours_streamed')
                        ->label('Hours Worked')
                        ->numeric()
                        ->step(0.25)
                        ->helperText('Total time you were on stream — used for payout calculation')
                        ->extraAttributes(['data-validation' => json_encode(['min' => 0, 'minMessage' => 'Hours must be at least 0'])]),
                    TextInput::make('number_of_shipments')
                        ->label('Number of Shipments')
                        ->integer()
                        ->helperText('Total shipments sent for this show')
                        ->extraAttributes(['data-validation' => json_encode(['min' => 0, 'minMessage' => 'Shipments must be at least 0'])]),
                    TextInput::make('number_of_packages_over_500')
                        ->label('Packages Over $500')
                        ->helperText('Shipments over $500 value — these incur an extra shipping surcharge')
                        ->integer()
                        ->extraAttributes(['data-validation' => json_encode(['min' => 0])]),
                    TextInput::make('pwe_count')
                        ->label('PWE Count')
                        ->helperText('Packages shipped PWE (PostagePaidEnvelope) — affects your payout')
                        ->integer()
                        ->visible(fn (?StreamerLogEntry $record) => $record?->streamer?->payout_type === 'pwe_labels')
                        ->extraAttributes(['data-validation' => json_encode(['min' => 0])]),
                    TextInput::make('label_count')
                        ->label('Label-Only Count')
                        ->helperText('Packages with label only (no PWE) — used to calculate your shipping pay')
                        ->integer()
                        ->visible(fn (?StreamerLogEntry $record) => $record?->streamer?->payout_type === 'pwe_labels')
                        ->extraAttributes(['data-validation' => json_encode(['min' => 0])]),
                ]),
            ]),

            Section::make('Revenue & Product Cost')
                ->description('Enter revenue and product costs. Your profit is calculated from these numbers.')
                ->disabled(fn (?StreamerLogEntry $record) => static::isLockedForCurrentUser($record))
                ->columnSpanFull()
                ->schema([
                Grid::make(2)->schema([
                    TextInput::make('gross_revenue')
                        ->label('Gross Revenue')
                        ->numeric()
                        ->prefix('$')
                        ->helperText('Auto-filled from show data — update if you need to correct it')
                        ->extraAttributes(['data-validation' => json_encode(['min' => 0, 'minMessage' => 'Revenue must be at least 0'])]),
                    TextInput::make('product_cost')
                        ->label('Product Cost Total')
                        ->numeric()
                        ->prefix('$')
                        ->helperText('Added up from the report\'s items at their inventory cost. Type over it only to correct it — adding or removing an item recalculates it.')
                        ->extraAttributes(['data-validation' => json_encode(['min' => 0, 'minMessage' => 'Cost must be at least 0'])]),
                ]),
            ]),

            Section::make('Inventory Assignment')
                ->description('Admin only: Assign inventory items from stock locations to this streamer log')
                ->visible(fn () => auth()->user()?->isAdmin())
                ->collapsed(true)
                ->columnSpanFull()
                ->schema([
                Grid::make(2)->schema([
                    Select::make('inventory_item_id')
                        ->label('Inventory Item')
                        ->options(fn () => InventoryItem::where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->placeholder('Select an item to pull from inventory')
                        ->helperText('Choose which product to pull stock from')
                        ->nullable(),
                    Select::make('inventory_location_id')
                        ->label('Stock Location')
                        ->options(fn (Get $get) =>
                            $get('inventory_item_id')
                                ? InventoryLocation::whereHas('stock', fn ($q) =>
                                    $q->where('inventory_item_id', $get('inventory_item_id'))
                                        ->where('quantity', '>', 0)
                                )
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                : collect()
                        )
                        ->searchable()
                        ->placeholder('Select a location with available stock')
                        ->helperText('Only locations with this item in stock are shown')
                        ->nullable()
                        ->visible(fn (Get $get) => !!$get('inventory_item_id')),
                    TextInput::make('inventory_quantity')
                        ->label('Quantity to Allocate')
                        ->numeric()
                        ->step(0.01)
                        ->minValue(0)
                        ->placeholder('Optional: amount pulled from inventory')
                        ->helperText('Track how much inventory was allocated for this log')
                        ->nullable(),
                ]),
            ]),

            Section::make('Notes')
                ->disabled(fn (?StreamerLogEntry $record) => static::isLockedForCurrentUser($record))
                ->columnSpanFull()
                ->schema([
                Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No reports')
            ->emptyStateDescription('Submitted streamer reports will appear here for review.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->extraAttributes(['data-sticky-header' => 'true', 'class' => 'vx-card-table'])
            ->modifyQueryUsing(fn (Builder $query, $livewire) => static::applyInboxSort($query, $livewire->inboxSort ?? '')
                ->with(['show.channel', 'show.streamers', 'streamer'])
                ->withCount(['items', 'items as uncosted_items_count' => fn (Builder $q) => $q->whereNull('inventory_item_id')->whereNull('unit_cost')]))
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->columns([
                \Filament\Tables\Columns\Layout\Stack::make([
                    \Filament\Tables\Columns\ViewColumn::make('report')
                        ->label('Report')
                        ->view('filament.tables.report-inbox.card')
                        ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $q) => $q
                            ->whereHas('show', fn (Builder $s) => $s->where('title', 'like', "%{$search}%"))
                            ->orWhereHas('streamer', fn (Builder $s) => $s->where('name', 'like', "%{$search}%")))),
                ]),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(StreamerLogEntry::statusLabels()),
                SelectFilter::make('date_range')
                    ->label('Date range')
                    ->options(['7' => 'Last 7 days', '30' => 'Last 30 days', 'this_month' => 'This month', 'last_month' => 'Last month'])
                    ->query(function (Builder $query, array $data): Builder {
                        $today = \App\Support\BusinessTime::local();
                        [$from, $to] = match ($data['value'] ?? null) {
                            '7' => [$today->copy()->subDays(6), $today],
                            '30' => [$today->copy()->subDays(29), $today],
                            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
                            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
                            default => [null, null],
                        };

                        return $from ? $query->whereHas('show', fn ($s) => $s->whereBetween('show_date', [$from->toDateString(), $to->toDateString() . ' 23:59:59'])) : $query;
                    }),
                SelectFilter::make('streamer_id')
                    ->label('Streamer')
                    ->options(fn () => Streamer::orderBy('name')->pluck('name', 'id')->all())
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isOwner() || auth()->user()?->isFulfillmentAdmin())
                    ->searchable(),
                SelectFilter::make('channel')
                    ->label('Channel')
                    ->options(fn () => \App\Models\WhatnotChannel::orderBy('name')->pluck('name', 'id')->toArray())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn ($q, $v) => $q->whereHas('show', fn ($s) => $s->where('whatnot_channel_id', $v)),
                    )),
                Filter::make('show_date')
                    ->columns(2)
                    ->columnSpan(['sm' => 2, 'lg' => 2])
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
            ->actions([
                // Every action sits on the card as its own button; which ones
                // show depends on where the report is. The main button is last.
                // Files a report the streamer never submitted, on their behalf —
                // through submitReport() so it is stamped, stock posts and the
                // review policy applies exactly as if they had pressed Submit.
                Action::make('streamer_review')
                    ->label('Submit for streamer')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('gray')
                    ->button()
                    ->outlined()
                    ->size('sm')
                    ->visible(fn (StreamerLogEntry $record) => $record->status === 'pending' && ! $record->isSubmitted()
                        && (auth()->user()?->isAdmin() || auth()->user()?->isOwner()))
                    ->requiresConfirmation()
                    ->modalHeading('Submit this report for the streamer?')
                    ->modalDescription('Files it as if the streamer had pressed Submit: it locks, stock posts and it goes to review (or approves itself, depending on your workflow settings). Use it when the streamer finished but forgot to submit.')
                    ->modalSubmitActionLabel('Submit report')
                    ->action(function (StreamerLogEntry $record): void {
                        $problems = $record->submitReport();

                        Notification::make()
                            ->title('Report submitted for the streamer')
                            ->body($problems === [] ? null : 'Inventory exceptions: ' . implode(' | ', $problems))
                            ->success()
                            ->send();
                    }),
                Action::make('fulfillment_review')
                    ->label('Fulfillment checked')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->button()
                    ->outlined()
                    ->size('sm')
                    ->visible(fn (StreamerLogEntry $record) => $record->needsFulfillmentReview()
                        && (auth()->user()?->isAdmin() || auth()->user()?->isOwner() || auth()->user()?->isFulfillmentAdmin()))
                    ->requiresConfirmation()
                    ->modalHeading('Confirm fulfillment review')
                    ->modalDescription('Confirms the PWE and label counts above are correct for this payout-type streamer, after the streamer and admin review are already done.')
                    ->action(function (StreamerLogEntry $record): void {
                        $record->update([
                            'fulfillment_reviewed_by' => auth()->id(),
                            'fulfillment_reviewed_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Fulfillment review recorded')
                            ->success()
                            ->send();
                    }),

                // Answering a streamer's "can I have this back?" from the list,
                // rather than opening each report to find the button.
                Action::make('approve_edit_request')
                    ->label('Reopen for edits')
                    ->icon('heroicon-o-lock-open')
                    ->color('warning')
                    ->button()
                    ->outlined()
                    ->size('sm')
                    ->visible(fn (StreamerLogEntry $record) => $record->hasPendingRevisionRequest()
                        && (auth()->user()?->isAdmin() || auth()->user()?->isOwner()))
                    ->requiresConfirmation()
                    ->modalHeading('Approve this edit request')
                    ->modalDescription(fn (StreamerLogEntry $record) => trim(
                        ($record->revision_reason ? "They said: “{$record->revision_reason}”\n\n" : '')
                        . 'The report stays as filed and its stock stays posted — the streamer just gets the edit window back.'
                    ))
                    ->modalSubmitActionLabel('Reopen')
                    ->action(function (StreamerLogEntry $record): void {
                        $record->reopenForEditing();

                        Notification::make()
                            ->title('Report reopened')
                            ->body('The streamer can edit it again and has been told.')
                            ->success()
                            ->send();
                    }),

                Action::make('decline_edit_request')
                    ->label('Decline request')
                    ->icon('heroicon-o-hand-raised')
                    ->color('gray')
                    ->button()
                    ->outlined()
                    ->size('sm')
                    ->visible(fn (StreamerLogEntry $record) => $record->hasPendingRevisionRequest()
                        && (auth()->user()?->isAdmin() || auth()->user()?->isOwner()))
                    ->form([
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label('Why not? (the streamer sees this)')
                            ->rows(3)
                            ->required(),
                    ])
                    ->action(function (StreamerLogEntry $record, array $data): void {
                        $record->declineRevisionRequest($data['reason']);

                        Notification::make()
                            ->title('Edit request declined')
                            ->body('The streamer has been told; the report stays as filed.')
                            ->success()
                            ->send();
                    }),

                // Send an already-reviewed/approved entry back to the streamer so
                // they can edit it again (admins/owner only).
                Action::make('send_back')
                    ->label('Request changes')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->button()
                    ->outlined()
                    ->size('sm')
                    ->visible(fn (StreamerLogEntry $record) => in_array($record->status, ['streamer_reviewed', 'admin_approved'])
                        && (auth()->user()?->isAdmin() || auth()->user()?->isOwner()))
                    ->form([
                        \Filament\Forms\Components\Textarea::make('notes')
                            ->label('What needs changing?')
                            ->rows(3)
                            ->placeholder('Tell the streamer what to fix before resubmitting.')
                            ->required(),
                    ])
                    ->modalHeading('Request changes from the streamer')
                    ->modalDescription('Reopens this entry for editing and returns any stock that was deducted when it was submitted.')
                    ->modalSubmitActionLabel('Request Changes')
                    ->action(function (StreamerLogEntry $record, array $data): void {
                        $record->sendBackToStreamer($data['notes']);

                        Notification::make()
                            ->title('Changes requested')
                            ->body('The streamer has been notified and any deducted stock was returned.')
                            ->success()
                            ->send();
                    }),
                // For reports started by mistake or while testing: the show goes
                // back to having no report at all.
                Action::make('reset_report')
                    ->label('Reset')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->button()
                    ->outlined()
                    ->size('sm')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isOwner())
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-arrow-path')
                    ->modalHeading('Reset this report?')
                    ->modalDescription(fn (StreamerLogEntry $record) => 'Deletes the report for "' . ($record->show?->title ?? 'this show') . '" and its logged items, puts back any stock it took, and removes draft pay worked out from it. The show goes back to Not started, and the streamer can log it again. This cannot be undone.')
                    ->modalSubmitActionLabel('Reset report')
                    ->action(function (StreamerLogEntry $record): void {
                        try {
                            $record->resetToNotStarted();
                        } catch (\RuntimeException $e) {
                            Notification::make()->title('Report not reset')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Report reset')->body('The show is back to Not started.')->success()->send();
                    }),
                Action::make('admin_approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->button()
                    ->outlined()
                    ->size('sm')
                    ->extraAttributes(['class' => 'vxi-approve'])
                    ->visible(fn (StreamerLogEntry $record) => $record->status === 'streamer_reviewed'
                        && (auth()->user()?->isAdmin() || auth()->user()?->isOwner()))
                    ->requiresConfirmation()
                    ->action(function (StreamerLogEntry $record): void {
                        // Through the model, not inline. The inline version set
                        // three columns and stopped: the report stayed unlocked,
                        // approval_status stayed 'pending_approval', inventory
                        // was never posted under the on_approval policy and the
                        // streamer was never told. Approving from the edit page
                        // did all four, so the same word meant two things
                        // depending on which screen you were standing on.
                        $problems = $record->approveByAdmin();

                        Notification::make()
                            ->title('Log entry approved')
                            ->body($problems === [] ? null : 'Inventory exceptions: ' . implode(' | ', $problems))
                            ->success()
                            ->send();
                    }),
                Action::make('open')
                    ->label(fn (StreamerLogEntry $record) => match (true) {
                        $record->hasPendingRevisionRequest() => 'View request',
                        $record->status === 'streamer_reviewed' && (auth()->user()?->isAdmin() || auth()->user()?->isOwner()) => 'Review',
                        $record->status === 'changes_requested' => 'View changes',
                        default => 'Open report',
                    })
                    ->button()
                    ->outlined()
                    ->size('sm')
                    ->color('primary')
                    ->extraAttributes(['class' => 'vxi-primary'])
                    ->url(fn (StreamerLogEntry $record) => static::getUrl('edit', ['record' => $record])),
            ])
            // Approving a week of logs one row at a time is the actual job on
            // this screen. There was a "bottom action bar" offering Approve,
            // Reject and Export in bulk, but it read a data-log-id attribute
            // nothing sets and posted to two routes that were never
            // registered, so every button either said "Please select logs" or
            // 404'd. These do the work, through the same model methods the row
            // actions use — so stock returns on a rejection, same as before.
            ->toolbarActions([
                BulkAction::make('bulk_admin_approve')
                    ->label('Approve selected')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isOwner())
                    ->requiresConfirmation()
                    ->modalHeading('Approve these log entries')
                    ->modalDescription('Only entries the streamer has submitted for review can be approved; anything else in the selection is skipped.')
                    ->modalSubmitActionLabel('Approve')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        $approved = $records
                            ->filter(fn (StreamerLogEntry $record) => $record->status === 'streamer_reviewed')
                            ->each(fn (StreamerLogEntry $record) => $record->approveByAdmin())
                            ->count();

                        $skipped = $records->count() - $approved;

                        Notification::make()
                            ->title($approved === 1 ? '1 log entry approved' : "{$approved} log entries approved")
                            ->body($skipped > 0 ? "{$skipped} skipped — not submitted for review." : null)
                            ->success()
                            ->send();
                    }),
                BulkAction::make('bulk_reset')
                    ->label('Reset selected')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isOwner())
                    ->requiresConfirmation()
                    ->modalHeading('Reset these reports?')
                    ->modalDescription('Each report and its logged items are deleted, any stock they took is put back, and draft pay worked out from them is removed. The shows go back to Not started. Reports whose pay is already on a submitted or paid run are skipped. This cannot be undone.')
                    ->modalSubmitActionLabel('Reset reports')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        $reset = 0;
                        $skipped = [];
                        foreach ($records as $record) {
                            try {
                                $record->resetToNotStarted();
                                $reset++;
                            } catch (\RuntimeException $e) {
                                $skipped[] = ($record->show?->title ?? 'Report #' . $record->id) . ': ' . $e->getMessage();
                            }
                        }

                        Notification::make()
                            ->title($reset === 1 ? '1 report reset' : "{$reset} reports reset")
                            ->body($skipped ? 'Skipped — ' . implode(' | ', $skipped) : null)
                            ->{$skipped ? 'warning' : 'success'}()
                            ->send();
                    }),
                BulkAction::make('bulk_send_back')
                    ->label('Request changes')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isOwner())
                    ->form([
                        \Filament\Forms\Components\Textarea::make('notes')
                            ->label('What needs changing?')
                            ->rows(3)
                            ->placeholder('Tell the streamers what to fix before resubmitting.')
                            ->helperText('The same note goes to every entry selected.')
                            ->required(),
                    ])
                    ->modalHeading('Request changes from the streamers')
                    ->modalDescription('Reopens each entry for editing and returns any stock that was deducted when it was submitted.')
                    ->modalSubmitActionLabel('Request Changes')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records, array $data): void {
                        $sent = $records
                            ->filter(fn (StreamerLogEntry $record) => in_array($record->status, ['streamer_reviewed', 'admin_approved'], true))
                            // The same method the row action uses. This one used
                            // to reset four columns of its own and leave
                            // submitted_at and locked_at alone, so an entry sent
                            // back in bulk told the streamer to fix it while
                            // staying locked against them doing so.
                            ->each(fn (StreamerLogEntry $record) => $record->sendBackToStreamer($data['notes']))
                            ->count();

                        $skipped = $records->count() - $sent;

                        Notification::make()
                            ->title($sent === 1 ? '1 entry sent back' : "{$sent} entries sent back")
                            ->body($skipped > 0 ? "{$skipped} skipped — not reviewed or approved yet." : null)
                            ->warning()
                            ->send();
                    }),
            ])
            ->recordUrl(fn (StreamerLogEntry $record) => static::getUrl('edit', ['record' => $record]))
            ->defaultSort('id', 'desc')
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::AboveContent)
            ->filtersFormColumns(['sm' => 2, 'lg' => 4])
            ->deferFilters(false)
            ->searchPlaceholder('Search show or streamer')
            ->paginated([6, 12, 24])
            ->defaultPaginationPageOption(6)
            ->deferLoading()
            ->persistFiltersInSession();
    }

    /** The inbox's Sort menu. Blank leaves the table's own default (newest first). */
    public static function applyInboxSort(Builder $query, string $sort): Builder
    {
        $showDate = \App\Models\Show::select('show_date')->whereColumn('shows.id', 'streamer_log_entries.show_id');

        return match ($sort) {
            'submitted_desc' => $query->orderByRaw('submitted_at is null')->orderByDesc('submitted_at'),
            'waiting_longest' => $query->orderByRaw('submitted_at is null')->orderBy('submitted_at'),
            'show_desc' => $query->orderByDesc($showDate),
            'show_asc' => $query->orderBy($showDate),
            default => $query,
        };
    }

    /**
     * What a report is still missing that pay or stock depends on, so an
     * admin sees it on the inbox card instead of finding out at pay time.
     * Reads counts the inbox query loads (items_count, uncosted_items_count).
     *
     * @return list<string>
     */
    public static function missingInfo(StreamerLogEntry $record): array
    {
        $show = $record->show;
        $missing = [];

        if (! $record->streamer_id) {
            $missing[] = 'No streamer assigned';
        }
        if ($show && $show->show_date && ! $show->show_date->isFuture()
            && (float) ($show->gross_revenue ?? 0) <= 0 && (float) ($show->whatnot_net ?? 0) == 0.0) {
            $missing[] = 'Whatnot earnings not imported';
        }

        $pay = $record->streamer_id ? static::payFor($record) : null;
        if ($pay && str_contains((string) $pay['notes'], 'Hours missing')) {
            $missing[] = 'Hours worked missing';
        } elseif (! $pay && $record->hours_streamed === null) {
            $missing[] = 'Hours worked missing';
        }

        if ($record->number_of_shipments === null && \App\Support\ProfitShareFormula::ratePerShipment() !== null
            && in_array($record->streamer?->payout_type, ['profit_share', 'hybrid'], true)) {
            $missing[] = 'Shipments not entered';
        }

        $items = $record->items_count ?? null;
        if ($items === 0 && $record->isSubmitted()) {
            $missing[] = 'No items logged';
        }
        $uncosted = (int) ($record->uncosted_items_count ?? 0);
        if ($uncosted > 0) {
            $missing[] = $uncosted === 1 ? '1 item has no cost' : "{$uncosted} items have no cost";
        }

        if ($record->streamer_id && ! $pay && $record->streamer) {
            $missing[] = 'Pay could not be worked out — check the streamer\'s pay setup';
        }

        return $missing;
    }

    private static ?\WeakMap $payMemo = null;

    /** The streamer's pay for this one report's show; see PayoutService::payForReport(). */
    public static function payFor(StreamerLogEntry $record): ?array
    {
        static::$payMemo ??= new \WeakMap;

        if (! static::$payMemo->offsetExists($record)) {
            static::$payMemo[$record] = app(\App\Services\PayoutService::class)->payForReport($record);
        }

        return static::$payMemo[$record];
    }

    public static function reportStartDate(): ?string
    {
        try {
            $raw = trim((string) \App\Models\Setting::get('show_reports_required_from', ''));
            return $raw === '' ? null : \Illuminate\Support\Carbon::parse($raw)->toDateString();
        } catch (\Throwable) { return null; }
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['show', 'streamer'])->inChannelContext();

        if ($from = static::reportStartDate()) {
            $query->whereHas('show', fn (Builder $q) => $q->whereDate('show_date', '>=', $from));
        }

        $user = auth()->user();
        if ($user?->isStreamer() && ! $user?->isAdmin() && ! $user?->isOwner()) {
            $streamerId = $user->streamer?->id;
            if ($streamerId) {
                // Every show the streamer was on — including shows co-hosted with
                // others — not only the entries where they're the named streamer.
                $query->whereHas('show.streamers', fn ($q) => $q->where('streamers.id', $streamerId));
            } else {
                $query->whereRaw('1 = 0'); // no linked streamer record — show nothing
            }
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsSoldRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStreamerLogEntries::route('/'),
            'edit'  => Pages\EditStreamerLogEntry::route('/{record}'),
        ];
    }
}
