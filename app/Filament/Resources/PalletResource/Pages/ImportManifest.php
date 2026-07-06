<?php

namespace App\Filament\Resources\PalletResource\Pages;

use App\Filament\Resources\PalletResource;
use App\Jobs\ParsePalletSlipJob;
use App\Models\AiTask;
use App\Models\InventoryItem;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\Setting;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\WithFileUploads;

class ImportManifest extends Page
{
    use WithFileUploads;

    protected static string $resource = PalletResource::class;

    protected static ?string $title = 'Import Packing Slip';

    public Pallet $record;

    /** @var 'upload'|'processing'|'verify'|'done' */
    public string $stage = 'upload';

    public $slipFile = null;

    public ?int $aiTaskId = null;

    public ?string $parseError = null;

    public bool $parseErrorIsTimeout = false;

    private const PENDING_TIMEOUT_MINUTES    = 5;
    private const PROCESSING_TIMEOUT_MINUTES = 10;

    /**
     * Lines shown in the verify stage — editable by the user before import.
     *
     * @var list<array{description:string, case_count:int|string, unit_cost:string, sku:string, barcode:string, matched_item_id:int|null, matched_item_name:string|null, create_new_item:bool}>
     */
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
    }

    // ── Stage 1 → 2: upload file and dispatch AI job ──────────────────────────

    public function parseSlip(): void
    {
        $this->validate(['slipFile' => 'required|file|max:20480|mimes:jpeg,jpg,png,gif,webp,pdf']);

        $ext  = $this->slipFile->getClientOriginalExtension();
        $path = storage_path('app/manifest-uploads/' . uniqid('slip_') . '.' . $ext);

        @mkdir(dirname($path), 0775, true);
        $this->slipFile->storeAs('manifest-uploads', basename($path), 'local');

        $task = AiTask::create([
            'type'          => 'parse_pallet_slip',
            'status'        => 'pending',
            'taskable_type' => Pallet::class,
            'taskable_id'   => $this->record->id,
            'triggered_by'  => auth()->id(),
            'input'         => ['pallet_id' => $this->record->id, 'file' => basename($path)],
        ]);

        ParsePalletSlipJob::dispatch($this->record->id, $task->id, $path)->onQueue('ai');

        $this->aiTaskId = $task->id;
        $this->stage    = 'processing';
        $this->slipFile = null;
    }

    // ── Stage 2: poll every 3 s until the job finishes ────────────────────────

    public function checkProcessing(): void
    {
        if (! $this->aiTaskId) {
            return;
        }

        $task = AiTask::find($this->aiTaskId);
        if (! $task) {
            return;
        }

        if ($task->status === 'completed') {
            $raw = $task->output['lines'] ?? [];
            $this->parsedLines = array_map(function ($l) {
                $item = $this->findMatchingItem($l['barcode'] ?? null, $l['sku'] ?? null, $l['description'] ?? null);
                return [
                    'description'       => $l['description'] ?? '',
                    'case_count'        => (string) ($l['case_count'] ?? 1),
                    'unit_cost'         => $l['unit_cost'] !== null ? number_format((float) $l['unit_cost'], 2, '.', '') : '',
                    'sku'               => $l['sku'] ?? '',
                    'barcode'           => $l['barcode'] ?? '',
                    'matched_item_id'   => $item?->id,
                    'matched_item_name' => $item?->name,
                    'create_new_item'   => false,
                ];
            }, $raw);
            $this->stage = 'verify';
            return;
        }

        if ($task->status === 'failed') {
            $this->parseError          = $task->error_message ?? 'AI parsing failed.';
            $this->parseErrorIsTimeout = false;
            $this->stage               = 'upload';
            return;
        }

        if ($task->status === 'processing' && (
            $task->started_at === null ||
            $task->started_at->lt(now()->subMinutes(self::PROCESSING_TIMEOUT_MINUTES)))) {
            $this->parseError          = 'AI processing timed out after ' . self::PROCESSING_TIMEOUT_MINUTES . ' minutes. The AI worker container may have crashed.';
            $this->parseErrorIsTimeout = true;
            $this->stage               = 'upload';
            return;
        }

        if ($task->status === 'pending' &&
            $task->created_at?->lt(now()->subMinutes(self::PENDING_TIMEOUT_MINUTES))) {
            $this->parseError          = 'AI job was not picked up after ' . self::PENDING_TIMEOUT_MINUTES . ' minutes. The AI worker container may not be running.';
            $this->parseErrorIsTimeout = true;
            $this->stage               = 'upload';
        }
    }

    // ── Verify stage helpers ──────────────────────────────────────────────────

    public function addLine(): void
    {
        $this->parsedLines[] = [
            'description' => '', 'case_count' => '1', 'unit_cost' => '', 'sku' => '', 'barcode' => '',
            'matched_item_id' => null, 'matched_item_name' => null, 'create_new_item' => false,
        ];
    }

    private function findMatchingItem(?string $barcode, ?string $sku, ?string $description): ?InventoryItem
    {
        if ($barcode && $item = InventoryItem::where('barcode', trim($barcode))->first()) {
            return $item;
        }
        if ($sku && $item = InventoryItem::where('sku', trim($sku))->first()) {
            return $item;
        }
        if ($description && $item = InventoryItem::where('name', trim($description))->first()) {
            return $item;
        }
        return null;
    }

    public function removeLine(int $idx): void
    {
        array_splice($this->parsedLines, $idx, 1);
        $this->parsedLines = array_values($this->parsedLines);
    }

    // ── Stage 3 → 4: import verified lines ───────────────────────────────────

    public function import(): void
    {
        $pallet   = $this->record;
        $nextLine = ($pallet->lines()->max('line_number') ?? 0) + 1;
        $created  = 0;
        $matched  = 0;

        DB::transaction(function () use ($pallet, &$nextLine, &$created, &$matched) {
            foreach ($this->parsedLines as $row) {
                $description = trim($row['description'] ?? '');
                if ($description === '') {
                    continue;
                }

                $unitCost = (float) str_replace(['$', ','], '', $row['unit_cost'] ?? '0');

                // Re-check match (user may have edited description/sku/barcode)
                $item = $this->findMatchingItem($row['barcode'] ?? null, $row['sku'] ?? null, $description);

                // If still unmatched and user wants to create a new item
                if (! $item && ! empty($row['create_new_item'])) {
                    $item = InventoryItem::create([
                        'name'      => $description,
                        'sku'       => ($row['sku'] ?? '') ?: null,
                        'barcode'   => ($row['barcode'] ?? '') ?: null,
                        'unit_cost' => $unitCost > 0 ? $unitCost : 0,
                        'is_active' => true,
                    ]);
                }

                PalletLine::create([
                    'pallet_id'         => $pallet->id,
                    'line_number'       => $nextLine++,
                    'description'       => $description,
                    'case_count'        => max(1, (int) ($row['case_count'] ?? 1)),
                    'unit_cost'         => $unitCost > 0 ? $unitCost : ($item?->unit_cost ?? 0),
                    'inventory_item_id' => $item?->id,
                ]);

                $created++;
                if ($item) {
                    $matched++;
                }
            }
        });

        $this->created   = $created;
        $this->matched   = $matched;
        $this->unmatched = $created - $matched;
        $this->stage     = 'done';
    }
}
