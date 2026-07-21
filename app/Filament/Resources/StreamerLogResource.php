<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StreamerLogResource\Pages;
use App\Filament\Resources\StreamerLogResource\RelationManagers;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Support\AdminModules;
use App\Support\NavVisibility;
use App\Support\StatusColor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Concerns\HasAdminNavVisibility;

class StreamerLogResource extends Resource
{
    use HasAdminNavVisibility;

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

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! AdminModules::isEnabled('streams') || NavVisibility::isHiddenForUser(static::class, $user)) {
            return false;
        }

        return $user?->isAdmin() || $user?->isStreamer();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /**
     * A streamer may only edit their entry (and its items) while it's still open.
     * Once an admin has approved it, it's view-only for the streamer until an
     * admin sends it back. Admins/owner can always edit.
     */
    public static function isLockedForCurrentUser(?StreamerLogEntry $record): bool
    {
        $user = auth()->user();
        if (! $record || $user?->isAdmin() || $user?->isOwner()) {
            return false;
        }

        return $record->status === 'admin_approved';
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
                ->disabled(fn (?StreamerLogEntry $record) => static::isLockedForCurrentUser($record))
                ->columnSpanFull()
                ->schema([
                Grid::make(2)->schema([
                    Toggle::make('hard_copy')
                        ->label('Hard Copy (physical log filed)'),
                    TextInput::make('hours_streamed')
                        ->label('Hours Streamed')
                        ->numeric()
                        ->step(0.25),
                    TextInput::make('number_of_shipments')
                        ->label('Number of Shipments')
                        ->integer(),
                    TextInput::make('number_of_packages_over_500')
                        ->label('Packages Over $500')
                        ->helperText('Triggers shipping surcharge')
                        ->integer(),
                    TextInput::make('pwe_count')
                        ->label('PWE Count')
                        ->helperText('Packages shipped PWE — used for payout')
                        ->integer()
                        ->visible(fn (?StreamerLogEntry $record) => $record?->streamer?->payout_type === 'pwe_labels'),
                    TextInput::make('label_count')
                        ->label('Label-Only Count')
                        ->helperText('Packages shipped with a label only — used for payout')
                        ->integer()
                        ->visible(fn (?StreamerLogEntry $record) => $record?->streamer?->payout_type === 'pwe_labels'),
                ]),
            ]),

            Section::make('Revenue & Product Cost')
                ->disabled(fn (?StreamerLogEntry $record) => static::isLockedForCurrentUser($record))
                ->columnSpanFull()
                ->schema([
                Grid::make(2)->schema([
                    TextInput::make('gross_revenue')
                        ->label('Gross Revenue')
                        ->numeric()
                        ->prefix('$')
                        ->helperText('Auto-filled from show — override if needed'),
                    TextInput::make('product_cost')
                        ->label('Product Cost Total')
                        ->numeric()
                        ->prefix('$')
                        ->helperText('Total wholesale cost of products sold (from calculations sheet)'),
                ]),
            ]),

            Section::make('Pay Breakdown')
                // Streamers can log their info and items, but their pay is set by
                // an admin — so these fields are read-only for non-admins.
                ->disabled(fn () => ! (auth()->user()?->isAdmin() || auth()->user()?->isOwner()))
                ->columnSpanFull()
                ->schema([
                Grid::make(2)->schema([
                    TextInput::make('profit_share_amount')
                        ->label('Profit Share Amount')
                        ->numeric()
                        ->prefix('$')
                        ->helperText('(Gross − Product Cost) × PS%'),
                    Toggle::make('profit_share_paid')
                        ->label('Profit Share Paid'),
                    TextInput::make('pwe_pay')
                        ->label('PWE Pay')
                        ->numeric()
                        ->prefix('$'),
                    TextInput::make('hourly_pay')
                        ->label('Hourly Pay')
                        ->numeric()
                        ->prefix('$'),
                    TextInput::make('tips_paid')
                        ->label('Tips Paid')
                        ->numeric()
                        ->prefix('$'),
                    TextInput::make('total_due')
                        ->label('Total Due')
                        ->numeric()
                        ->prefix('$'),
                    TextInput::make('total_paid')
                        ->label('Total Paid')
                        ->numeric()
                        ->prefix('$'),
                    TextInput::make('business_net_rev')
                        ->label('Business Net Rev')
                        ->numeric()
                        ->prefix('$'),
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
            ->columns([
                TextColumn::make('show.show_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('show.title')
                    ->label('Show')
                    ->limit(35)
                    ->searchable(),
                TextColumn::make('streamer.name')
                    ->label('Streamer')
                    ->searchable(),
                TextColumn::make('show.channel.name')
                    ->label('Channel')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => StreamerLogEntry::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => StatusColor::for($state)),
                TextColumn::make('hours_streamed')
                    ->label('Hours')
                    ->numeric(),
                TextColumn::make('number_of_shipments')
                    ->label('Shipments')
                    ->numeric(),
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
                TextColumn::make('profit_share_amount')
                    ->label('Profit Share')
                    ->money('USD'),
                TextColumn::make('total_due')
                    ->label('Total Due')
                    ->money('USD'),
                TextColumn::make('total_paid')
                    ->label('Total Paid')
                    ->money('USD'),
            ])
            ->filters([
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
                Action::make('fulfillment_review')
                    ->label('Fulfillment Reviewed')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->visible(fn (StreamerLogEntry $record) => $record->needsFulfillmentReview()
                        && (auth()->user()?->isAdmin() || auth()->user()?->isOwner()))
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
                    ->label('Send Back')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (StreamerLogEntry $record) => in_array($record->status, ['streamer_reviewed', 'admin_approved'])
                        && (auth()->user()?->isAdmin() || auth()->user()?->isOwner()))
                    ->requiresConfirmation()
                    ->modalHeading('Send back to streamer')
                    ->modalDescription('Reopens this entry so the streamer can edit it again. Its status returns to pending.')
                    ->action(function (StreamerLogEntry $record): void {
                        $record->update([
                            'status'                  => 'pending',
                            'streamer_reviewed_at'    => null,
                            'reviewed_by'             => null,
                            'reviewed_at'             => null,
                            'fulfillment_reviewed_by' => null,
                            'fulfillment_reviewed_at' => null,
                        ]);
                        Notification::make()->title('Sent back to streamer')->success()->send();
                    }),

                // Opens the full edit page (with the Items Sold editor). Read-only
                // for a streamer once the entry is approved.
                Action::make('open')
                    ->label(fn (StreamerLogEntry $record) => static::isLockedForCurrentUser($record) ? 'View' : 'Open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (StreamerLogEntry $record) => static::getUrl('edit', ['record' => $record])),
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
            'edit'  => Pages\EditStreamerLogEntry::route('/{record}/edit'),
        ];
    }
}
