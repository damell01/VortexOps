<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\ScannerReceivingSession;
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

class ReceivingSessionsHistory extends Page implements HasTable
{
    use HasModuleAccess, InteractsWithTable;

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Receiving Sessions';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-clock';
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
        return 'Session History';
    }

    public static function getNavigationSort(): ?int
    {
        return 999;
    }

    public function getView(): string
    {
        return 'filament.pages.receiving-sessions-history';
    }

    public function getSubheading(): ?string
    {
        return 'View all receiving sessions with detailed item breakdowns and PDF export';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ScannerReceivingSession::query()->with(['pallet.vendor', 'user']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                TextColumn::make('pallet.reference')
                    ->label('Pallet / PO')
                    ->searchable(['pallets.reference'])
                    ->url(fn (ScannerReceivingSession $record) => $record->pallet?->id
                        ? route('filament.admin.resources.pallets.view', ['record' => $record->pallet->id])
                        : null),

                TextColumn::make('pallet.vendor.name')
                    ->label('Vendor')
                    ->searchable(['vendors.name']),

                TextColumn::make('user.name')
                    ->label('Operator')
                    ->searchable(['users.name']),

                TextColumn::make('mode')
                    ->label('Mode')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'barcode' => 'blue',
                        'camera' => 'purple',
                        default => 'gray',
                    }),

                TextColumn::make('items_scanned')
                    ->label('Items Scanned')
                    ->alignRight(),

                TextColumn::make('duration')
                    ->label('Duration')
                    ->state(function (ScannerReceivingSession $record) {
                        if (!$record->started_at || !$record->ended_at) {
                            return 'Ongoing';
                        }
                        $minutes = $record->started_at->diffInMinutes($record->ended_at);
                        if ($minutes < 60) {
                            return "{$minutes}m";
                        }
                        $hours = intval($minutes / 60);
                        $mins = $minutes % 60;
                        return "{$hours}h {$mins}m";
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (ScannerReceivingSession $record) => $record->isActive() ? 'warning' : 'success')
                    ->state(fn (ScannerReceivingSession $record) => $record->isActive() ? 'Active' : 'Completed'),
            ])
            ->actions([
                Action::make('view_items')
                    ->label('Items')
                    ->icon('heroicon-o-list-bullet')
                    ->modalHeading('Session Items')
                    ->modalContent(fn (ScannerReceivingSession $record) => view('modals.session-items', [
                        'session' => $record->load('pallet.lines.inventoryItem'),
                    ]))
                    ->modalSubmitActionLabel('Close'),

                Action::make('export_pdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function (ScannerReceivingSession $record) {
                        try {
                            $reportService = app(ReceivingReportService::class);
                            $filePath = $reportService->generateSessionReport($record);
                            $fullPath = Storage::disk('public')->path($filePath);

                            return response()->download($fullPath, "session-{$record->id}.pdf");
                        } catch (\Exception $e) {
                            // Report will be handled by Filament
                        }
                    }),
            ])
            ->bulkActions([]);
    }
}
