<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\ReceivingSessionResource\Pages;
use App\Models\ReceivingSession;
use App\Models\Vendor;
use App\Support\AdminModules;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class ReceivingSessionResource extends Resource
{
    use HasModuleAccess;

    protected static string $moduleSlug  = 'purchasing';
    protected static string $featureSlug = 'receiving_sessions';

    protected static ?string $model = ReceivingSession::class;

    protected static ?string $navigationLabel = 'Receiving Sessions';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('purchasing');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Session Details')->schema([
                Grid::make(2)->schema([
                    Select::make('vendor_id')
                        ->label('Vendor')
                        ->relationship('vendor', 'name')
                        ->searchable()
                        ->preload(),

                    Select::make('status')
                        ->options(ReceivingSession::statusLabels())
                        ->default(ReceivingSession::STATUS_PENDING)
                        ->required(),

                    TextInput::make('invoice_number')
                        ->label('Invoice / Reference #'),

                    TextInput::make('purchase_order')
                        ->label('Purchase Order #'),
                ]),

                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('invoice_number')
                    ->label('Invoice')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('purchase_order')
                    ->label('PO #')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'completed'  => 'success',
                        'reviewing'  => 'warning',
                        'parsing'    => 'info',
                        'cancelled'  => 'danger',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ReceivingSession::statusLabels()[$state] ?? $state),

                TextColumn::make('total_lines')
                    ->label('Lines')
                    ->alignCenter(),

                TextColumn::make('auto_matched_count')
                    ->label('Auto')
                    ->alignCenter()
                    ->color('success'),

                TextColumn::make('review_required_count')
                    ->label('Review')
                    ->alignCenter()
                    ->color('warning'),

                TextColumn::make('new_product_count')
                    ->label('New')
                    ->alignCenter()
                    ->color('info'),

                TextColumn::make('receivedByUser.name')
                    ->label('Received By')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Started')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(ReceivingSession::statusLabels()),

                SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'name'),
            ])
            ->recordUrl(fn (ReceivingSession $record) => Pages\ReviewReceivingSession::getUrl(['record' => $record]))
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListReceivingSessions::route('/'),
            'create' => Pages\CreateReceivingSession::route('/create'),
            'review' => Pages\ReviewReceivingSession::route('/{record}/review'),
        ];
    }
}
