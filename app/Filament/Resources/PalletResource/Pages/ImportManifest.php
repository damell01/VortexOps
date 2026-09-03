<?php

namespace App\Filament\Resources\PalletResource\Pages;

use App\Filament\Resources\PalletResource;
use App\Jobs\ParsePalletSlipJob;
use App\Models\AiTask;
use App\Models\InventoryItem;
use App\Models\Pallet;
use App\Models\PalletLine;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;

class ImportManifest extends Page
{
    use WithFileUploads;

    protected static string $resource = PalletResource::class;
    protected static ?string $title = 'AI Manifest Review';

    public Pallet $record;

    /** @var 'upload'|'processing'|'verify'|'done' */
    public string $stage = 'upload';
    public $slipFile = null;
    public ?int $aiTaskId = null;
    public ?string $parseError = null;
    public bool $parseErrorIsTimeout = false;

    /** @var list<array<string,mixed>> */
    public array $parsedLines = [];

    public int $created = 0;
    public int $matched = 0;
    public int $unmatched = 0;

    public function getView(): string
    {
        return 'filament.pages.import-manifest';
    }

    public function mount(Pallet $record): void
    {
        $this->record = $record->load('vendor');

        $task = AiTask::query()
            ->where('type', 'parse_pallet_slip')
            ->where('taskable_type', Pallet::class)
            ->where('taskable_id', $record->id)
            ->latest('id')
            ->first();

        if ($task) {
            $this->aiTaskId = $task->id;
            $this->applyTaskState($task);
        }
    }

    public function parseSlip(): mixed
    {
        $this->validate([
            'slipFile' => 'required|file|max:20480|mimes:jpeg,jpg,png,gif,webp,pdf,csv,txt,xls,xlsx',
        ]);

        $ext = strtolower($this->slipFile->getClientOriginalExtension());
        $filename = uniqid('manifest_') . '.' . $ext;
        $relativePath = $this->slipFile->storeAs('manifest-uploads', $filename, 'local');

        if (! $relativePath || ! Storage::disk('local')->exists($relativePath)) {
            throw new \RuntimeException('Manifest upload could not be saved.');
        }

        $path = Storage::disk('local')->path($relativePath);

        $task = AiTask::create([
            'type' => 'parse_pallet_slip',
            'status' => 'pending',
            'taskable_type' => Pallet::class,
            'taskable_id' => $this->record->id,
            'triggered_by' => auth()->id(),
            'input' => [
                'pallet_id' => $this->record->id,
                'file' => $filename,
                'stored_path' => $relativePath,
                'extension' => $ext,
                'background_only' => true,
                'review_required' => true,
            ],
        ]);

        ParsePalletSlipJob::dispatch($this->record->id, $task->id, $path)->onQueue('ai');

        $this->aiTaskId = $task->id;
        $this->stage = 'processing';
        $this->slipFile = null;
        $this->parseError = null;

        Notification::make()
            ->title('AI manifest job started')
            ->body('You can leave this page. VortexOps will add the extracted manifest lines in the background and notify you when item matching is ready to review.')
            ->success()
            ->send();

        return redirect()->to(PalletResource::getUrl('view', ['record' => $this->record]));
    }

    public function checkProcessing(): void
    {
        if (! $this->aiTaskId) return;

        $task = AiTask::find($this->aiTaskId);
        if (! $task) return;

        $this->applyTaskState($task);
    }

    private function applyTaskState(AiTask $task): void
    {
        if ($task->status === 'completed') {
            $this->parsedLines = array_values($task->output['lines'] ?? []);
            $this->stage = 'verify';
            $this->parseError = null;
            return;
        }

        if (in_array($task->status, ['pending', 'processing'], true)) {
            $this->stage = 'processing';
            return;
        }

        if ($task->status === 'failed') {
            $this->parseError = $task->error_message ?? 'Manifest analysis failed.';
            $this->parseErrorIsTimeout = str_contains(strtolower((string) $this->parseError), 'timeout');
            $this->stage = 'upload';
        }
    }

    public function startOver(): void
    {
        $this->stage = 'upload';
        $this->aiTaskId = null;
        $this->parsedLines = [];
        $this->parseError = null;
        $this->parseErrorIsTimeout = false;
    }

    public function addLine(): void
    {
        $this->parsedLines[] = [
            'description' => '',
            'case_count' => '1',
            'quantity_per_case' => '1',
            'unit_cost' => '',
            'sku' => '',
            'barcode' => '',
            'matched_item_id' => null,
            'matched_item_name' => null,
            'match_confidence' => '',
            'match_confidence_score' => 0,
            'match_stage' => '',
            'match_reasons' => [],
            'alternatives' => [],
            'create_new_item' => true,
            'pallet_line_id' => null,
        ];
    }

    public function chooseMatch(int $index, int $itemId): void
    {
        if (! isset($this->parsedLines[$index])) return;

        $item = InventoryItem::find($itemId);
        if (! $item) return;

        $this->parsedLines[$index]['matched_item_id'] = $item->id;
        $this->parsedLines[$index]['matched_item_name'] = $item->name;
        $this->parsedLines[$index]['create_new_item'] = false;
    }

    public function chooseCreateNew(int $index): void
    {
        if (! isset($this->parsedLines[$index])) return;

        $this->parsedLines[$index]['matched_item_id'] = null;
        $this->parsedLines[$index]['matched_item_name'] = null;
        $this->parsedLines[$index]['create_new_item'] = true;
    }

    private function quickItemLookup(?string $barcode, ?string $sku, string $description): ?InventoryItem
    {
        if ($barcode && $item = InventoryItem::where('barcode', trim($barcode))->first()) return $item;
        if ($sku && $item = InventoryItem::where('sku', trim($sku))->first()) return $item;
        if ($description && $item = InventoryItem::where('name', trim($description))->first()) return $item;
        return null;
    }

    public function removeLine(int $idx): void
    {
        if (! isset($this->parsedLines[$idx])) return;

        $lineId = $this->parsedLines[$idx]['pallet_line_id'] ?? null;
        if ($lineId) {
            PalletLine::query()
                ->where('id', $lineId)
                ->where('pallet_id', $this->record->id)
                ->whereDoesntHave('cases', fn ($q) => $q->where('status', '!=', 'expected'))
                ->delete();
        }

        array_splice($this->parsedLines, $idx, 1);
        $this->parsedLines = array_values($this->parsedLines);
    }

    public function import(): mixed
    {
        $pallet = $this->record;
        $nextLine = ($pallet->lines()->max('line_number') ?? 0) + 1;
        $processed = 0;
        $matched = 0;

        DB::transaction(function () use ($pallet, &$nextLine, &$processed, &$matched) {
            foreach ($this->parsedLines as $row) {
                $description = trim($row['description'] ?? '');
                if ($description === '') continue;

                $unitCost = (float) str_replace(['$', ','], '', $row['unit_cost'] ?? '0');
                $item = ($row['matched_item_id'] ?? null)
                    ? InventoryItem::find($row['matched_item_id'])
                    : $this->quickItemLookup($row['barcode'] ?? null, $row['sku'] ?? null, $description);

                if (! $item && ! empty($row['create_new_item'])) {
                    $item = InventoryItem::create([
                        'name' => $description,
                        'sku' => ($row['sku'] ?? '') ?: null,
                        'barcode' => ($row['barcode'] ?? '') ?: null,
                        'unit_cost' => $unitCost > 0 ? $unitCost : 0,
                        'is_active' => true,
                    ]);
                }

                $attributes = [
                    'description' => $description,
                    'vendor_description' => $description,
                    'case_count' => max(1, (int) ($row['case_count'] ?? 1)),
                    'quantity_per_case' => max(1, (float) ($row['quantity_per_case'] ?? 1)),
                    'unit_cost' => $unitCost > 0 ? $unitCost : ($item?->unit_cost ?? 0),
                    'inventory_item_id' => $item?->id,
                    'match_confidence' => $item ? (float) ($row['match_confidence_score'] ?? 1) : null,
                    'match_stage' => $item ? (($row['match_stage'] ?? '') ?: 'manual_review') : 'ai_unmatched',
                    'match_reasons' => $row['match_reasons'] ?? [],
                    'matched_at' => $item ? now() : null,
                    'matched_by' => $item ? auth()->id() : null,
                ];

                $lineId = $row['pallet_line_id'] ?? null;
                $line = $lineId
                    ? PalletLine::query()->where('pallet_id', $pallet->id)->find($lineId)
                    : null;

                if ($line) {
                    $line->update($attributes);
                } else {
                    $line = PalletLine::create(array_merge($attributes, [
                        'pallet_id' => $pallet->id,
                        'line_number' => $nextLine++,
                    ]));
                }

                $processed++;
                if ($item) $matched++;
            }
        });

        $this->created = $processed;
        $this->matched = $matched;
        $this->unmatched = $processed - $matched;
        $this->stage = 'done';

        Notification::make()
            ->title('Manifest review saved')
            ->body("{$processed} manifest lines saved · {$matched} mapped to inventory · {$this->unmatched} remain unmapped.")
            ->success()
            ->send();

        return redirect()->to(PalletResource::getUrl('view', ['record' => $pallet]));
    }
}
