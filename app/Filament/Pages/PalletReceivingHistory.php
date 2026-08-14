<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\Pallet;
use App\Services\InventoryCostService;
use App\Services\ReceivingReportService;
use App\Support\AdminModules;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use App\Support\NavVisibility;

class PalletReceivingHistory extends Page implements HasTable
{
    use HasModuleAccess, InteractsWithTable;

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Pallet Receives';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-inbox-stack';
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Nav visibility is configured per role in Settings; without this
        // check an override here silently ignored that setting and the link
        // stayed in the sidebar regardless.
        if (NavVisibility::isHiddenForUser(static::class, auth()->user())) {
            return false;
        }

        return false;
    }

    public static function getNavigationLabel(): string
    {
        return 'Pallet History';
    }

    public static function getNavigationSort(): ?int
    {
        return 999;
    }

    public function getView(): string
    {
        return 'filament.pages.pallet-receiving-history';
    }

    public function getSubheading(): ?string
    {
        return 'View all received pallets with items, costs, and PDF reports';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Pallet::query()->with(['vendor', 'lines', 'scannerSessions']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('staged_at')
                    ->label('Received Date')
                    ->dateTime('M d, Y')
                    ->sortable(),

                TextColumn::make('reference')
                    ->label('PO / Reference')
                    ->searchable()
                    ->url(fn (Pallet $record) => route('filament.admin.resources.pallets.view', ['record' => $record->id])),

                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable(['vendors.name']),

                TextColumn::make('line_items_total')
                    ->label('Items')
                    ->alignCenter()
                    ->state(fn (Pallet $record) => count($record->lines)),

                TextColumn::make('total_cost')
                    ->label('Total Cost')
                    ->money('USD', locale: 'en_US')
                    ->alignRight(),

                TextColumn::make('avg_cost')
                    ->label('Avg Unit Cost')
                    ->alignRight()
                    ->state(function (Pallet $record) {
                        $totalQty = $record->lines->sum('quantity');
                        $totalCost = $record->lines->sum(fn ($line) => $line->quantity * $line->unit_cost);
                        return $totalQty > 0 ? '$' . number_format($totalCost / $totalQty, 2) : '—';
                    }),

                TextColumn::make('stage')
                    ->label('Stage')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'created' => 'gray',
                        'staged' => 'blue',
                        'receiving' => 'warning',
                        'received' => 'success',
                        'processed' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'shipping' => 'info',
                        'receiving' => 'warning',
                        'received' => 'success',
                        'processed' => 'success',
                        default => 'gray',
                    }),
            ])
            ->actions([
                Action::make('view_items')
                    ->label('Items')
                    ->icon('heroicon-o-list-bullet')
                    ->modalHeading('Pallet Items')
                    ->modalContent(fn (Pallet $record) => view('modals.pallet-items', [
                        'pallet' => $record->load(['lines.inventoryItem', 'vendor']),
                    ]))
                    ->modalSubmitActionLabel('Close'),

                Action::make('view_sessions')
                    ->label('Sessions')
                    ->icon('heroicon-o-clock')
                    ->modalHeading('Receiving Sessions')
                    ->modalContent(fn (Pallet $record) => view('modals.pallet-sessions', [
                        'sessions' => $record->scannerSessions()->with('user')->get(),
                    ]))
                    ->modalSubmitActionLabel('Close'),

                Action::make('export_pdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function (Pallet $record) {
                        try {
                            $reportService = app(ReceivingReportService::class);
                            $filePath = $reportService->generatePalletReport($record);
                            $fullPath = Storage::disk('public')->path($filePath);

                            return response()->download($fullPath, "pallet-{$record->reference}.pdf");
                        } catch (\Exception $e) {
                            // Report will be handled by Filament
                        }
                    }),
            ])
            ->bulkActions([]);
    }
}
