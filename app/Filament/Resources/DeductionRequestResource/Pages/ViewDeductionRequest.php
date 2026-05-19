<?php

namespace App\Filament\Resources\DeductionRequestResource\Pages;

use App\Filament\Resources\DeductionRequestResource;
use App\Models\DeductionRequest;
use App\Models\DeductionRequestLine;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Services\DeductionApprovalService;
use App\Services\DeductionRejectionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;
use Throwable;

class ViewDeductionRequest extends EditRecord
{
    protected static string $resource = DeductionRequestResource::class;

    public function form(Schema $schema): Schema
    {
        /** @var DeductionRequest $request */
        $request  = $this->record;
        $show     = $request->show;
        $editable = ! in_array($request->status, ['processed', 'rejected']);

        return $schema->components([
            Section::make('Show Summary')
                ->columns(3)
                ->schema([
                    Placeholder::make('show_title')
                        ->label('Show Title')
                        ->content($show->title ?? '—'),

                    Placeholder::make('show_date')
                        ->label('Date')
                        ->content($show->show_date?->format('M j, Y') ?? '—'),

                    Placeholder::make('show_status')
                        ->label('Show Status')
                        ->content(\App\Models\Show::statusLabels()[$show->status] ?? $show->status),

                    Placeholder::make('gross_revenue')
                        ->label('Gross Revenue')
                        ->content('$' . number_format((float) $show->gross_revenue, 2)),

                    Placeholder::make('units_sold')
                        ->label('Units Sold')
                        ->content($show->units_sold),

                    Placeholder::make('streamer_name')
                        ->label('Streamer')
                        ->content($request->streamer->name ?? '—'),
                ]),

            Section::make('AI Mapping Notes')
                ->visible(fn () => ! empty($request->ai_mapping_notes))
                ->schema([
                    Placeholder::make('ai_mapping_notes')
                        ->label('')
                        ->content($request->ai_mapping_notes ?? ''),
                ]),

            Section::make('Rejection Reason')
                ->visible(fn () => $request->status === 'rejected' && ! empty($request->rejection_reason))
                ->schema([
                    Placeholder::make('rejection_reason')
                        ->label('')
                        ->content($request->rejection_reason ?? ''),
                ]),

            Section::make('Deduction Lines')
                ->schema([
                    Repeater::make('lines_data')
                        ->label('')
                        ->default(function () use ($request) {
                            return $request->lines->map(fn (DeductionRequestLine $line) => [
                                'id'                    => $line->id,
                                'inventory_item_id'     => $line->inventory_item_id,
                                'inventory_location_id' => $line->inventory_location_id,
                                'raw_description'       => $line->raw_description,
                                'ai_confidence'         => $line->ai_confidence,
                                'ai_reason'             => $line->ai_reason,
                                'quantity_suggested'    => (float) $line->quantity_suggested,
                                'quantity_approved'     => (float) $line->quantity_approved,
                                'unit_cost_snapshot'    => (float) $line->unit_cost_snapshot,
                                'line_total'            => (float) $line->line_total,
                            ])->all();
                        })
                        ->schema([
                            Grid::make(4)->schema([
                                Select::make('inventory_item_id')
                                    ->label('Item')
                                    ->options(InventoryItem::where('is_active', true)->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(2)
                                    ->disabled(! $editable),

                                Select::make('inventory_location_id')
                                    ->label('Location')
                                    ->options(InventoryLocation::where('status', 'active')->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->disabled(! $editable),

                                Select::make('ai_confidence')
                                    ->label('AI Confidence')
                                    ->options(DeductionRequestLine::confidenceLabels())
                                    ->disabled(),
                            ]),

                            Grid::make(4)->schema([
                                TextInput::make('quantity_suggested')
                                    ->label('Suggested Qty')
                                    ->numeric()
                                    ->disabled(),

                                TextInput::make('quantity_approved')
                                    ->label('Approved Qty')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required()
                                    ->disabled(! $editable),

                                TextInput::make('unit_cost_snapshot')
                                    ->label('Unit Cost ($)')
                                    ->numeric()
                                    ->disabled(! $editable),

                                TextInput::make('line_total')
                                    ->label('Line Total ($)')
                                    ->numeric()
                                    ->disabled(),
                            ]),

                            TextInput::make('ai_reason')
                                ->label('AI Reason')
                                ->disabled()
                                ->columnSpanFull(),
                        ])
                        ->addable($editable)
                        ->deletable($editable)
                        ->reorderable(false)
                        ->columnSpanFull(),
                ]),

            Section::make('Ops Notes')->schema([
                Textarea::make('ops_notes')
                    ->label('Ops Notes')
                    ->rows(2)
                    ->disabled(! $editable)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        $request = $this->record;

        return [
            Action::make('approve')
                ->label('Approve & Process')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => in_array($request->status, ['pending', 'draft']))
                ->requiresConfirmation()
                ->modalHeading('Approve Deduction Request')
                ->modalDescription('This will execute inventory deductions for all approved lines via InventoryService. This action cannot be undone.')
                ->action(function () use ($request) {
                    try {
                        $this->persistLines($request);

                        if ($opsNotes = $this->data['ops_notes'] ?? null) {
                            $request->update(['ops_notes' => $opsNotes]);
                        }

                        app(DeductionApprovalService::class)->approve($request);

                        Notification::make()
                            ->title('Deduction request approved and inventory deducted')
                            ->success()
                            ->send();

                        $this->redirect(DeductionRequestResource::getUrl('index'));
                    } catch (Throwable $e) {
                        Log::error('Deduction approval failed', [
                            'deduction_request_id' => $request->id,
                            'message' => $e->getMessage(),
                        ]);

                        Notification::make()
                            ->title('Approval failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => in_array($request->status, ['pending', 'draft']))
                ->form([
                    Textarea::make('rejection_reason')
                        ->label('Rejection Reason')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) use ($request) {
                    app(DeductionRejectionService::class)->reject($request, $data['rejection_reason']);

                    Notification::make()
                        ->title('Deduction request rejected — show returned to Pending Review')
                        ->warning()
                        ->send();

                    $this->redirect(DeductionRequestResource::getUrl('index'));
                }),
        ];
    }

    private function persistLines(DeductionRequest $request): void
    {
        $linesData = $this->data['lines_data'] ?? [];

        foreach ($linesData as $lineData) {
            if (! empty($lineData['id'])) {
                $line = DeductionRequestLine::find($lineData['id']);
                if ($line) {
                    $qtyApproved = (float) ($lineData['quantity_approved'] ?? $line->quantity_approved);
                    $unitCost    = (float) ($lineData['unit_cost_snapshot'] ?? $line->unit_cost_snapshot);
                    $line->update([
                        'inventory_item_id'     => $lineData['inventory_item_id'] ?? $line->inventory_item_id,
                        'inventory_location_id' => $lineData['inventory_location_id'] ?? $line->inventory_location_id,
                        'quantity_approved'     => $qtyApproved,
                        'unit_cost_snapshot'    => $unitCost,
                        'line_total'            => round($qtyApproved * $unitCost, 2),
                        'ops_overridden'        => $qtyApproved != (float) $line->quantity_suggested,
                    ]);
                }
            } else {
                $qtyApproved = (float) ($lineData['quantity_approved'] ?? 0);
                $unitCost    = (float) ($lineData['unit_cost_snapshot'] ?? 0);
                DeductionRequestLine::create([
                    'deduction_request_id'  => $request->id,
                    'inventory_item_id'     => $lineData['inventory_item_id'],
                    'inventory_location_id' => $lineData['inventory_location_id'],
                    'quantity_suggested'    => $qtyApproved,
                    'quantity_approved'     => $qtyApproved,
                    'unit_cost_snapshot'    => $unitCost,
                    'line_total'            => round($qtyApproved * $unitCost, 2),
                    'ai_confidence'         => 'manual',
                    'ops_overridden'        => true,
                ]);
            }
        }

        $request->refresh();
    }
}
