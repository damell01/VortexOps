<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FeatureFlagResource\Pages;
use App\Models\FeatureFlag;
use App\Services\FeatureFlagService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Filament\Concerns\HasAdminNavVisibility;

class FeatureFlagResource extends Resource
{
    use HasAdminNavVisibility;

    protected static ?string $model = FeatureFlag::class;

    protected static ?string $navigationLabel = 'Feature Flags';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-beaker';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Developer';
    }

    public static function getNavigationSort(): ?int
    {
        return 99;
    }

    public static function canAccess(): bool
    {
        $devEmail = config('app.developer_email');
        return ! empty($devEmail) && auth()->user()?->email === $devEmail;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $devEmail = config('app.developer_email');
        return ! empty($devEmail) && auth()->user()?->email === $devEmail;
    }

    public static function canCreate(): bool   { return false; }
    public static function canDelete($r): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->schema([
                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->emptyStateHeading('No feature flags')
            ->emptyStateDescription('Create a flag to gate experimental features per role or user.')
            ->emptyStateIcon('heroicon-o-flag')
            ->columns([
                TextColumn::make('label')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('key')
                    ->searchable()
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage('Copied!')
                    ->sortable(),
                TextColumn::make('description')
                    ->wrap()
                    ->toggleable(),
                IconColumn::make('enabled')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->sortable(),
            ])
            ->actions([
                Action::make('toggle')
                    ->label('Toggle')
                    ->icon(fn (FeatureFlag $record) => $record->enabled ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (FeatureFlag $record) => $record->enabled ? 'danger' : 'success')
                    ->action(function (FeatureFlag $record): void {
                        $record->enabled = ! $record->enabled;
                        $record->save();
                        FeatureFlagService::flush();

                        Notification::make()
                            ->title($record->enabled ? "Flag '{$record->key}' enabled" : "Flag '{$record->key}' disabled")
                            ->success()
                            ->send();
                    }),
                EditAction::make()->label('Edit Description'),
            ])
            ->defaultSort('label')
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeatureFlags::route('/'),
            'edit'  => Pages\EditFeatureFlag::route('/{record}/edit'),
        ];
    }
}
