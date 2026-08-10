<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\WhatnotChannelResource\Pages;
use App\Models\WhatnotChannel;
use App\Support\AdminModules;
use App\Support\StatusColor;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class WhatnotChannelResource extends Resource
{
    use HasModuleAccess;

    protected static string $moduleSlug  = 'operations';

    protected static ?string $model = WhatnotChannel::class;

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'whatnot_username'];
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-tv';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('operations');
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getNavigationLabel(): string
    {
        return 'Whatnot Channels';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Channel Details')->columnSpanFull()->schema([
                Grid::make(4)->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('whatnot_username')
                        ->label('Whatnot Username')
                        ->maxLength(255),
                    TextInput::make('channel_url')
                        ->label('Channel URL')
                        ->url()
                        ->maxLength(500),
                    Select::make('status')
                        ->options(WhatnotChannel::statusLabels())
                        ->required()
                        ->default('active'),
                ]),
                Toggle::make('include_in_import')
                    ->label('Include in Show Import')
                    ->helperText('When enabled, this channel will be scraped when running the Whatnot import.')
                    ->default(true)
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]),
            Section::make('Branding')
                ->description('Shown at the top of the app whenever this channel is the active channel in the switcher. Leave blank to keep the default app branding.')
                ->columnSpanFull()
                ->schema([
                    FileUpload::make('logo_path')
                        ->label('Channel Logo')
                        ->image()
                        ->disk('public')
                        ->directory('channel-logos')
                        ->visibility('public')
                        ->maxSize(2048),
                    TextInput::make('display_title')
                        ->label('Display Title')
                        ->maxLength(255)
                        ->placeholder('Defaults to the app name'),
                ]),
        ]);
    }

    /**
     * shows/streamers/inventory_locations/whatnot_syncs all nullOnDelete on
     * whatnot_channel_id — no cascade destruction, but deleting a channel
     * that's still attributed on shows or streamers would silently orphan
     * that attribution across the business. Block while either exists.
     */
    public static function canDelete(Model $record): bool
    {
        return (auth()->user()?->isAdmin() ?? false)
            && ! $record->shows()->exists()
            && ! $record->streamers()->exists();
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No channels')
            ->emptyStateDescription('Add your Whatnot channels to start importing shows.')
            ->emptyStateIcon('heroicon-o-tv')
            ->deferLoading()
            ->columns([
                ImageColumn::make('logo_path')
                    ->label('')
                    ->circular(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('whatnot_username')
                    ->label('Username')
                    ->searchable(),
                TextColumn::make('channel_url')
                    ->label('URL')
                    ->url(fn ($record) => $record->channel_url)
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => WhatnotChannel::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => StatusColor::for($state)),
                IconColumn::make('include_in_import')
                    ->label('Import')
                    ->boolean()
                    ->trueIcon('heroicon-o-arrow-down-tray')
                    ->falseIcon('heroicon-o-no-symbol')
                    ->trueColor('success')
                    ->falseColor('gray'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(WhatnotChannel::statusLabels()),
            ])
            ->actions([
                ViewAction::make()
                    ->size('sm')
                    ->iconButton(),
                EditAction::make()
                    ->size('sm')
                    ->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (WhatnotChannel $record) => static::canDelete($record))
                    ->tooltip(fn (WhatnotChannel $record) => static::canDelete($record) ? null : 'Has shows or streamers attributed to it — can\'t be deleted while those exist.'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function (Collection $records): void {
                            $deletable = $records->filter(fn (WhatnotChannel $record) => static::canDelete($record));
                            $blocked   = $records->count() - $deletable->count();

                            $deletable->each->delete();

                            if ($blocked > 0) {
                                Notification::make()
                                    ->title($deletable->count() . ' channel(s) deleted')
                                    ->body("{$blocked} skipped — still have shows or streamers attributed.")
                                    ->warning()
                                    ->send();
                            } else {
                                Notification::make()->title($deletable->count() . ' channel(s) deleted')->success()->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->striped()
            ->persistFiltersInSession()
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWhatnotChannels::route('/'),
            'create' => Pages\CreateWhatnotChannel::route('/create'),
            'view' => Pages\ViewWhatnotChannel::route('/{record}'),
            'edit' => Pages\EditWhatnotChannel::route('/{record}/edit'),
        ];
    }
}
