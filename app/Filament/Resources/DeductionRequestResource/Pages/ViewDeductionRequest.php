<?php

namespace App\Filament\Resources\DeductionRequestResource\Pages;

use App\Filament\Resources\DeductionRequestResource;
use App\Models\DeductionRequest;
use App\Models\DeductionRequestLine;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Services\DeductionApprovalService;
use App\Services\DeductionRejectionService;
use App\Services\ProductMatchingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ViewDeductionRequest extends EditRecord
{
    protected static string $resource = DeductionRequestResource::class;

    public function form(Schema $schema): Schema
    {
        /** @var DeductionRequest $request */
        $request  = $this->record;
        $show     = $request->show->loadMissing('orders');
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

            Section::make('Revenue Outlier Warning')
                ->visible(fn () => $show->isRevenueOutlier())
                ->schema([
                    Placeholder::make('revenue_outlier_notice')
                        ->label('')
                        ->content('📈 This show\'s gross revenue looks unusual compared to this streamer\'s recent shows. Double-check the imported figures before approving payouts off them.'),
                ]),

            Section::make('Items Sold')
                ->description('Reference — imported from Whatnot. Use these to assign inventory items to each deduction line below.')
                ->visible(fn () => $show->orders->isNotEmpty())
                ->collapsible()
                ->collapsed(fn () => $request->lines->isNotEmpty())
                ->schema([
                    Placeholder::make('orders_table')
                        ->label('')
                        ->content(function () use ($show): \Illuminate\Support\HtmlString {
                            $rows = $show->orders
                                ->sortBy('lot_number')
                                ->map(fn ($o) =>
                                    '<tr>' .
                                    '<td style="padding:4px 10px;border-bottom:1px solid #eee">' . ($o->lot_number ?? '—') . '</td>' .
                                    '<td style="padding:4px 10px;border-bottom:1px solid #eee">' . e($o->item_name ?? '—') . '</td>' .
                                    '<td style="padding:4px 10px;border-bottom:1px solid #eee">' . e($o->buyer_display_name ?? $o->buyer_username ?? '—') . '</td>' .
                                    '<td style="padding:4px 10px;border-bottom:1px solid #eee;text-align:right">' . $o->quantity . '</td>' .
                                    '<td style="padding:4px 10px;border-bottom:1px solid #eee;text-align:right">$' . number_format((float) $o->unit_price, 2) . '</td>' .
                                    '</tr>'
                                )->join('');

                            return new \Illuminate\Support\HtmlString('
                                <div style="overflow-x:auto">
                                <table style="width:100%;border-collapse:collapse;font-size:12px">
                                    <thead>
                                        <tr style="background:var(--color-primary-50,#f5f3ff)">
                                            <th style="padding:5px 10px;text-align:left;font-weight:600">Lot</th>
                                            <th style="padding:5px 10px;text-align:left;font-weight:600">Item</th>
                                            <th style="padding:5px 10px;text-align:left;font-weight:600">Buyer</th>
                                            <th style="padding:5px 10px;text-align:right;font-weight:600">Qty</th>
                                            <th style="padding:5px 10px;text-align:right;font-weight:600">Sale Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>' . $rows . '</tbody>
                                </table>
                                </div>
                            ');
                        }),
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
                    Placeholder::make('lines_summary_table')
                        ->label('')
                        ->columnSpanFull()
                        ->content(function () use ($request): HtmlString {
                            $lines = $request->lines->load('inventoryItem');
                            if ($lines->isEmpty()) {
                                return new HtmlString('<p style="color:#9ca3af;font-size:13px">No lines yet.</p>');
                            }

                            $rows = $lines->map(function (DeductionRequestLine $line): string {
                                $desc     = e($line->raw_description ?? '—');
                                $item     = e($line->inventoryItem?->name ?? '');
                                $itemCell = $item
                                    ? "<span style=\"font-weight:600;color:#111827\">{$item}</span>"
                                    : '<span style="color:#dc2626;font-weight:600">⚠ Unmatched</span>';
                                $qty    = number_format((float) $line->quantity_approved, 0);
                                $cost   = '$' . number_format((float) $line->unit_cost_snapshot, 2);
                                $total  = '$' . number_format((float) $line->line_total, 2);
                                $rowBg  = $line->inventoryItem ? '' : 'background:#fff7f7;';

                                return "<tr style=\"{$rowBg}border-bottom:1px solid #f3f4f6\">
                                    <td style=\"padding:6px 10px;font-size:12px;color:#374151;max-width:220px;word-break:break-word\">{$desc}</td>
                                    <td style=\"padding:6px 10px;font-size:12px\">{$itemCell}</td>
                                    <td style=\"padding:6px 10px;font-size:12px;text-align:right;color:#374151\">{$qty}</td>
                                    <td style=\"padding:6px 10px;font-size:12px;text-align:right;color:#374151\">{$cost}</td>
                                    <td style=\"padding:6px 10px;font-size:12px;text-align:right;font-weight:600;color:#111827\">{$total}</td>
                                </tr>";
                            })->join('');

                            $totalCogs = '$' . number_format((float) $lines->sum('line_total'), 2);
                            $matched   = $lines->whereNotNull('inventory_item_id')->count();
                            $total     = $lines->count();
                            $unmatched = $total - $matched;

                            $unmatchedBadge = $unmatched
                                ? "<span style=\"display:inline-flex;align-items:center;gap:3px;padding:1px 8px;border-radius:9999px;background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;font-size:10px;font-weight:700\">⚠ Unmatched&nbsp;{$unmatched}</span>"
                                : '';

                            return new HtmlString("
                                <div style=\"margin-bottom:12px;display:flex;flex-wrap:wrap;align-items:center;gap:6px\">
                                    {$unmatchedBadge}
                                    <span style=\"margin-left:auto;font-size:11px;color:#6b7280\">{$matched}/{$total} matched · Total COGS: <strong style=\"color:#111827\">{$totalCogs}</strong></span>
                                </div>
                                <div style=\"overflow-x:auto;border:1px solid #e5e7eb;border-radius:8px\">
                                <table style=\"width:100%;border-collapse:collapse\">
                                    <thead>
                                        <tr style=\"background:#f9fafb\">
                                            <th style=\"padding:6px 10px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em\">Raw Description</th>
                                            <th style=\"padding:6px 10px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em\">Matched Item</th>
                                            <th style=\"padding:6px 10px;text-align:right;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em\">Qty</th>
                                            <th style=\"padding:6px 10px;text-align:right;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em\">Unit Cost</th>
                                            <th style=\"padding:6px 10px;text-align:right;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em\">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>{$rows}</tbody>
                                </table>
                                </div>
                            ");
                        }),

                    Repeater::make('lines_data')
                        ->label('')
                        ->default(function () use ($request) {
                            return $request->lines->load('inventoryItem')->map(fn (DeductionRequestLine $line) => [
                                'id'                    => $line->id,
                                'inventory_item_id'     => $line->inventory_item_id,
                                'inventory_item_name'   => $line->inventoryItem?->name,
                                'inventory_location_id' => $line->inventory_location_id,
                                'raw_description'       => $line->raw_description,
                                'quantity_suggested'    => (float) $line->quantity_suggested,
                                'quantity_approved'     => (float) $line->quantity_approved,
                                'unit_cost_snapshot'    => (float) $line->unit_cost_snapshot,
                                'line_total'            => (float) $line->line_total,
                            ])->all();
                        })
                        ->schema([
                            Grid::make(8)->schema([
                                Select::make('inventory_item_id')
                                    ->label('Item')
                                    ->options(InventoryItem::where('is_active', true)->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(5)
                                    ->disabled(! $editable),

                                Select::make('inventory_location_id')
                                    ->label('Location')
                                    ->options(InventoryLocation::where('status', 'active')->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(3)
                                    ->disabled(! $editable),
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
                        ])
                        ->itemLabel(function (array $state): string {
                            $desc = $state['raw_description'] ?? '?';
                            $item = $state['inventory_item_name'] ?? null;
                            return $item
                                ? "{$desc} → {$item}"
                                : "⚠ {$desc} — Unmatched";
                        })
                        ->collapsible()
                        ->collapsed()
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
            Action::make('fast_approve')
                ->label('Fast-Track Approve')
                ->icon('heroicon-o-bolt')
                ->color('success')
                ->visible(function () use ($request): bool {
                    if (! in_array($request->status, ['pending', 'draft'])) {
                        return false;
                    }
                    $lines = $request->lines;
                    return $lines->isNotEmpty()
                        && $lines->whereNull('inventory_item_id')->isEmpty()
                        && $lines->whereNull('inventory_location_id')->isEmpty();
                })
                ->requiresConfirmation()
                ->modalHeading('Fast-Track Approve')
                ->modalDescription(function () use ($request): string {
                    $count = $request->lines->count();
                    return "All {$count} " . ($count === 1 ? 'line is' : 'lines are') . " mapped. Approve now using current quantities — no edits needed.";
                })
                ->action(function () use ($request): void {
                    try {
                        DB::transaction(fn () => app(DeductionApprovalService::class)->approve($request));

                        Notification::make()
                            ->title('Deduction request approved and inventory deducted')
                            ->success()
                            ->send();

                        $this->redirect(DeductionRequestResource::getUrl('index'));
                    } catch (Throwable $e) {
                        Log::error('Fast-track approval failed', [
                            'deduction_request_id' => $request->id,
                            'message' => $e->getMessage(),
                        ]);

                        Notification::make()
                            ->title('Approval failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->duration(8000)
                            ->send();
                    }
                }),

            Action::make('approve')
                ->label('Approve & Process')
                ->icon('heroicon-o-check-circle')
                ->color('info')
                ->visible(fn () => in_array($request->status, ['pending', 'draft']))
                ->requiresConfirmation()
                ->modalHeading('Approve Deduction Request')
                ->modalDescription('This will execute inventory deductions for all approved lines via InventoryService. This action cannot be undone.')
                ->action(function () use ($request) {
                    try {
                        DB::transaction(function () use ($request) {
                            $this->persistLines($request);

                            if ($opsNotes = $this->data['ops_notes'] ?? null) {
                                $request->update(['ops_notes' => $opsNotes]);
                            }

                            app(DeductionApprovalService::class)->approve($request);
                        });

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
                            ->duration(8000)
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
                    $originalItemId = $line->inventory_item_id;
                    $newItemId      = $lineData['inventory_item_id'] ?? $originalItemId;
                    $qtyApproved    = (float) ($lineData['quantity_approved'] ?? $line->quantity_approved);
                    $unitCost       = (float) ($lineData['unit_cost_snapshot'] ?? $line->unit_cost_snapshot);

                    $line->update([
                        'inventory_item_id'     => $newItemId,
                        'inventory_location_id' => $lineData['inventory_location_id'] ?? $line->inventory_location_id,
                        'quantity_approved'     => $qtyApproved,
                        'unit_cost_snapshot'    => $unitCost,
                        'line_total'            => round($qtyApproved * $unitCost, 2),
                        'ops_overridden'        => $qtyApproved != (float) $line->quantity_suggested,
                    ]);

                    // Auto-learn: ops changed the matched item — save as a manual alias
                    // so future identical descriptions resolve instantly in Stage 1
                    if ($newItemId && $newItemId != $originalItemId && $line->raw_description) {
                        try {
                            $product = Product::find($newItemId);
                            if ($product) {
                                app(ProductMatchingService::class)->confirmMatch(
                                    $line->raw_description,
                                    $product,
                                    1.0,
                                    'manual',
                                    auth()->id(),
                                );
                            }
                        } catch (\Throwable $e) {
                            Log::debug("Failed to save manual alias for '{$line->raw_description}': " . $e->getMessage());
                        }
                    }
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

        $request->refresh()->load('lines.inventoryItem');
    }
}
