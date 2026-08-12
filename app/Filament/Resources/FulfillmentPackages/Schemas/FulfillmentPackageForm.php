<?php

namespace App\Filament\Resources\FulfillmentPackages\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Schemas\Schema;

class FulfillmentPackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Package Information')
                    ->components([
                        TextInput::make('tracking_number')
                            ->label('Tracking Number')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Select::make('carrier')
                            ->options([
                                'usps' => 'USPS',
                                'ups' => 'UPS',
                                'fedex' => 'FedEx',
                                'dhl' => 'DHL',
                                'other' => 'Other',
                            ])
                            ->searchable(),
                        Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'shipped' => 'Shipped',
                                'delivered' => 'Delivered',
                                'returned' => 'Returned',
                            ])
                            ->default('pending')
                            ->required(),
                        DateTimePicker::make('shipped_at')
                            ->label('Shipped Date & Time'),
                    ]),
                Section::make('Notes')
                    ->components([
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
