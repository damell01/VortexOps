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

    protected static ?string $navigationLabel = 'Streamer Log';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Streams';
    }

    public static function getNavigationSort(): ?int
    {
        return 40;
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
    public static function shouldRegisterNavigation(): bool
    {
        // A role granted this page on Roles & Permissions gets its link too;
        // access without a way to reach it is only half a grant.
        if (\App\Support\RoleAccess::grants(static::class)) {
            return true;
        }

        $user = auth()->user();

        return ($user?->isAdmin() || $user?->isOwner())
            && AdminModules::isEnabled('streams')
            && ! NavVisibility::isHiddenForUser(static::class, $user);
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
                ->description('Record key metrics from your show — hours streamed, shipments, and package counts.')
                ->disabled(fn (?StreamerLogEntry $record) => static::isLockedForCurrentUser($record))
                ->columnSpanFull()
                ->schema([
                Grid::make(2)->schema([
                    Toggle::make('hard_copy')
                        ->label('Hard Copy (physical log filed)')
                        ->helperText('Check if you filed a physical log sheet for this show'),
                    TextInput::make('hours_streamed')
                        ->label('Hours Streamed')
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
            ->emptyStateHeading('No log entries')
            ->emptyStateDescription('Streamer show logs land here for review and approval.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->extraAttributes(['data-sticky-header' => 'true'])
            ->columns([
                TextColumn::make('show.show_date')
                    ->label('Date')
                    ->date()
                    ->sortable()
                    ->extraCellAttributes(['class' => 'vx-nowrap']),
                TextColumn::make('show.title')
                    ->label('Show')
                    ->limit(35)
                    ->searchable()
                    ->extraCellAttributes(['class' => 'vx-col-title'])
                    ->extraHeaderAttributes(['class' => 'vx-col-title']),
                TextColumn::make('streamer.name')
                    ->label('Streamer')
                    ->searchable()
                    ->extraCellAttributes(['class' => 'vx-col-tight'])
                    ->extraHeaderAttributes(['class' => 'vx-col-tight']),
                TextColumn::make('show.channel.name')
                    ->label('Channel')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->formatStateUsing(fn ($state) => view('components.status-badge', [
                        'status' => $state,
                        'label' => StreamerLogEntry::statusLabels()[$state] ?? ucfirst(str_replace('_', ' ', $state)),
                    ])->render())
                    ->description(fn (StreamerLogEntry $record) => $record->hasPendingRevisionRequest()
                        ? 'Streamer asked to reopen: ' . ($record->revision_reason ?: 'no reason given')
                        : null)
                    ->html(),
                TextColumn::make('next_action')
                    ->label('Next Action')
                    ->state(fn (StreamerLogEntry $record): string => match ($record->status) {
                        'pending' => 'Add items & costs',
                        'changes_requested' => 'Streamer to revise & resubmit',
                        'streamer_reviewed' => 'Admin review pending',
                        'admin_approved' => 'Payout ready' . ($record->needsFulfillmentReview() ? ' (needs fulfillment review)' : ''),
                        default => 'Review',
                    })
                    ->badge()
                    ->color(fn (StreamerLogEntry $record): string => match ($record->status) {
                        'pending' => 'warning',
                        'changes_requested' => 'danger',
                        'streamer_reviewed' => 'info',
                        'admin_approved' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('hours_streamed')
                    ->label('Hours')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('number_of_shipments')
                    ->label('Shipments')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('pwe_count')
                    ->label('PWE')
                    ->numeric()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('label_count')
                    ->label('Labels')
                    ->numeric()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('fulfillment_reviewed_at')
                    ->label('Fulfillment')
                    ->visible(fn ($record) => $record?->streamer?->payout_type === 'pwe_labels')
                    ->boolean()
                    ->getStateUsing(fn (StreamerLogEntry $record) => $record->fulfillment_reviewed_at !== null)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('gross_revenue')
                    ->label('Gross Rev')
                    ->money('USD')
                    ->toggleable(),
                TextColumn::make('product_cost')
                    ->label('Product Cost')
                    ->money('USD')
                    ->toggleable(),
                TextColumn::make('total_due')
                    ->label('Total Due')
                    ->money('USD')
                    ->extraCellAttributes(['class' => 'vx-nowrap']),
                TextColumn::make('total_paid')
                    ->label('Total Paid')
                    ->money('USD')
                    ->extraCellAttributes(['class' => 'vx-nowrap']),
            ])
            ->filters([
                // A request buried in a list nobody filters is the same dead
                // end the streamer was already in, one table further along.
                \Filament\Tables\Filters\Filter::make('revision_requested')
                    ->label('Streamer asked to reopen')
                    ->query(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->whereNotNull('revision_requested_at')),
                SelectFilter::make('status')
                    ->options(StreamerLogEntry::statusLabels()),
                SelectFilter::make('streamer_id')
                    ->label('Streamer')
                    ->options(Streamer::orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
                SelectFilter::make('channel')
                    ->label('Channel')
                    ->options(fn () => \App\Models\WhatnotChannel::orderBy('name')->pluck('name', 'id')->toArray())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn ($q, $v) => $q->whereHas('show', fn ($s) => $s->where('whatnot_channel_id', $v)),
                    )),
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
            ->actions([
                Action::make('streamer_review')
                    ->label('Streamer Reviewed')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn (StreamerLogEntry $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Mark as Reviewed by Streamer')
                    ->modalDescription('Confirm the streamer has reviewed and signed off on this log entry.')
                    ->action(function (StreamerLogEntry $record): void {
                        $record->status = 'streamer_reviewed';
                        $record->streamer_reviewed_at = now();
                        $record->save();

                        Notification::make()
                            ->title('Log marked as reviewed by streamer')
                            ->success()
                            ->send();
                    }),
                Action::make('admin_approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (StreamerLogEntry $record) => $record->status === 'streamer_reviewed'
                        && (auth()->user()?->isAdmin() || auth()->user()?->isOwner()))
                    ->requiresConfirmation()
                    ->action(function (StreamerLogEntry $record): void {
                        $record->status = 'admin_approved';
                        $record->reviewed_by = auth()->id();
                        $record->reviewed_at = now();
                        $record->save();

                        Notification::make()
                            ->title('Log entry approved')
                            ->success()
                            ->send();
                    }),
                // Approve and Request Changes stay on the row — they're the
                // whole job of this screen. The rest goes behind the overflow
                // menu so the actions column stops squeezing Show Title.
                ActionGroup::make([
                Action::make('fulfillment_review')
                    ->label('Fulfillment Reviewed')
                    ->icon('heroicon-o-truck')
                    ->color('info')
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

                // Send an already-reviewed/approved entry back to the streamer so
                // they can edit it again (admins/owner only).
                Action::make('send_back')
                    ->label('Request Changes')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
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
                        // rejectByAdmin() is what puts the deducted stock back
                        // and notifies the streamer. The old inline update
                        // skipped it, so sending an entry back silently left
                        // its inventory deducted.
                        $record->rejectByAdmin($data['notes']);

                        $record->update([
                            // 'pending' was indistinguishable from "never
                            // started", so the Changes Requested tab and tile
                            // were always empty and the streamer got no signal.
                            'status'                  => 'changes_requested',
                            'streamer_reviewed_at'    => null,
                            'reviewed_by'             => null,
                            'reviewed_at'             => null,
                            'fulfillment_reviewed_by' => null,
                            'fulfillment_reviewed_at' => null,
                            // Reopen it for editing.
                            'submitted_at'            => null,
                            'locked_at'               => null,
                            // If the streamer asked for this, they have their
                            // answer — leaving the flag up would keep it in the
                            // "waiting to be reopened" list after reopening it.
                            'revision_requested_at'   => null,
                            'revision_reason'         => null,
                        ]);

                        Notification::make()
                            ->title('Changes requested')
                            ->body('The streamer has been notified and any deducted stock was returned.')
                            ->success()
                            ->send();
                    }),

                // Opens the full edit page (with the Items Sold editor). Read-only
                // for a streamer once the entry is approved.
                ]),

                Action::make('open')
                    ->label(fn (StreamerLogEntry $record) => static::isLockedForCurrentUser($record) ? 'View' : 'Open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->iconButton()
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
                            ->each(function (StreamerLogEntry $record): void {
                                $record->status      = 'admin_approved';
                                $record->reviewed_by = auth()->id();
                                $record->reviewed_at = now();
                                $record->save();
                            })
                            ->count();

                        $skipped = $records->count() - $approved;

                        Notification::make()
                            ->title($approved === 1 ? '1 log entry approved' : "{$approved} log entries approved")
                            ->body($skipped > 0 ? "{$skipped} skipped — not submitted for review." : null)
                            ->success()
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
                            ->each(function (StreamerLogEntry $record) use ($data): void {
                                // rejectByAdmin() is what returns the deducted
                                // stock and notifies the streamer.
                                $record->rejectByAdmin($data['notes']);

                                $record->update([
                                    'status'               => 'changes_requested',
                                    'streamer_reviewed_at' => null,
                                    'reviewed_by'          => null,
                                    'reviewed_at'          => null,
                                ]);
                            })
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
            ->striped()
            ->deferLoading()
            ->persistFiltersInSession();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['show', 'streamer'])->inChannelContext();

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
