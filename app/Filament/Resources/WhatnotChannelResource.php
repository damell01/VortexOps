<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WhatnotChannelResource\Pages;
use App\Models\WhatnotChannel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WhatnotChannelResource extends Resource
{
    protected static ?string $model = WhatnotChannel::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-tv';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Operations';
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
            Section::make('Channel Details')->schema([
                Grid::make(2)->schema([
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
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        default => 'gray',
                    }),
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
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
