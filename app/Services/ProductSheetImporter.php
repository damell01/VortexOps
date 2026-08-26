<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Reading a product cost sheet into the catalogue.
 *
 * This was a console command, and the logic is here now because two callers
 * need it: the command, and the screen that shows you what an import would do
 * before it does it. A preview built from a second implementation would be a
 * preview of something else — the only useful guarantee is that the rows you
 * approved are the rows that get written, by the same code.
 *
 * Nothing here writes until apply() is called. plan() is deliberately pure.
 */
class ProductSheetImporter
{
    /** The worksheet the original workbook keeps its products on. */
    public const DEFAULT_SHEET = 'Product cost ref sheet';

    /** How many rows a preview will render before it stops. */
    public const PREVIEW_LIMIT = 500;

    /** Header text → the key it fills. */
    private const EXPECTED_HEADERS = [
        'name'       => 'PRODUCT NAME',
        'sku'        => 'SKU',
        'type'       => 'Type',
        'sold_as'    => 'Auction or BIN?',
        'cost'       => 'Cost',
        'sale_price' => 'Sale price / Target',
    ];

    /**
     * The worksheets in a file, so a person can be asked which one rather than
     * being told the sheet they have is the wrong one.
     *
     * @return array<int, string>
     */
    public function sheetNames(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);

        return array_values($reader->listWorksheetNames($path));
    }

    /**
     * Rows from one worksheet, cleaned but not yet matched against anything.
     *
     * @return array<int, array{line:int,name:string,sku:?string,type:?string,sold_as:?string,cost:?float,sale_price:?float,warnings:array<int,string>}>
     */
    public function read(string $path, ?string $sheet = null): array
    {
        $sheet     = $sheet ?: self::DEFAULT_SHEET;
        $available = $this->sheetNames($path);

        if (! in_array($sheet, $available, true)) {
            throw new RuntimeException(sprintf(
                'This file has no worksheet called "%s". It has: %s.',
                $sheet,
                implode(', ', $available) ?: 'nothing readable',
            ));
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([$sheet]);

        $raw = $reader->load($path)->getSheet(0)->toArray(null, false, false, false);

        if ($raw === []) {
            return [];
        }

        $columns = $this->mapHeaders(array_shift($raw));
        $rows    = [];

        foreach ($raw as $offset => $line) {
            $name = trim((string) ($line[$columns['name']] ?? ''));

            // The sheet runs on for hundreds of empty rows past the last
            // product; a blank name is the only reliable end marker.
            if ($name === '') {
                continue;
            }

            $warnings = [];

            foreach (['cost' => 'Cost', 'sale_price' => 'Sale price / Target'] as $key => $label) {
                $cell = $line[$columns[$key]] ?? null;

                // A formula cell reads as "=F2-E2", and (float) on that is
                // 0.00 — a silent, plausible, wrong number. It is skipped, and
                // saying so is the difference between a skipped cell and a
                // cell someone thinks imported fine.
                if (is_string($cell) && str_starts_with(trim($cell), '=')) {
                    $warnings[] = $label . ' holds a formula, so it was skipped.';
                } elseif (filled($cell) && $this->money($cell) === null) {
                    $warnings[] = $label . ' is not a number, so it was skipped.';
                }
            }

            $rows[] = [
                // +2: one for the header row, one because people count from 1.
                'line'       => $offset + 2,
                'name'       => $name,
                'sku'        => $this->text($line[$columns['sku']] ?? null),
                'type'       => $this->text($line[$columns['type']] ?? null),
                'sold_as'    => $this->text($line[$columns['sold_as']] ?? null),
                'cost'       => $this->money($line[$columns['cost']] ?? null),
                'sale_price' => $this->money($line[$columns['sale_price']] ?? null),
                'warnings'   => $warnings,
            ];
        }

        return $rows;
    }

    /**
     * What an import would do, row by row, writing nothing.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{rows: array<int, array<string, mixed>>, summary: array{create:int,update:int,unchanged:int,total:int,warnings:int}}
     */
    public function plan(array $rows, bool $overwrite = false): array
    {
        $planned = [];
        $seen    = [];
        $counts  = ['create' => 0, 'update' => 0, 'unchanged' => 0, 'total' => 0, 'warnings' => 0];

        foreach ($rows as $row) {
            [$product, $matchedBy] = $this->match($row);

            $attributes = $this->attributesFor($row, $product, $overwrite);
            $action     = $product === null ? 'create' : ($attributes === [] ? 'unchanged' : 'update');

            $warnings = $row['warnings'] ?? [];

            // Two rows for one product is the sheet's problem, not the
            // catalogue's, and it only shows up as "the second one won".
            $key = Str::lower(trim($row['sku'] ?: $row['name']));

            if (isset($seen[$key])) {
                $warnings[] = 'The same product appears on line ' . $seen[$key] . ' as well — the later row wins.';
            }

            $seen[$key] = $row['line'] ?? null;

            $planned[] = [
                'line'       => $row['line'] ?? null,
                'name'       => $row['name'],
                'sku'        => $row['sku'],
                'action'     => $action,
                'matched_by' => $matchedBy,
                'match'      => $product ? ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku] : null,
                'changes'    => $this->changes($attributes, $product),
                'warnings'   => $warnings,
            ];

            $counts[$action]++;
            $counts['total']++;
            $counts['warnings'] += $warnings === [] ? 0 : 1;
        }

        return ['rows' => $planned, 'summary' => $counts];
    }

    /**
     * Write the plan. Same matching, same attributes — this only adds the save.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created:int,updated:int,unchanged:int}
     */
    public function apply(array $rows, bool $overwrite = false): array
    {
        $created = $updated = $unchanged = 0;

        foreach ($rows as $row) {
            [$product] = $this->match($row);

            $attributes = $this->attributesFor($row, $product, $overwrite);

            if ($product === null) {
                Product::create($attributes + ['name' => $row['name'], 'is_active' => true]);
                $created++;
                continue;
            }

            if ($attributes === []) {
                $unchanged++;
                continue;
            }

            $product->fill($attributes)->save();
            $updated++;
        }

        return ['created' => $created, 'updated' => $updated, 'unchanged' => $unchanged];
    }

    /**
     * Find each expected header, wherever it sits.
     *
     * Positional reading breaks silently the first time someone inserts a
     * column, and the failure looks like a data problem rather than a layout
     * one — costs landing in the name field, or every price reading null.
     *
     * @param  array<int, mixed>  $header
     * @return array<string, int|null>
     */
    private function mapHeaders(array $header): array
    {
        $normalise = fn ($v) => Str::of((string) $v)->lower()->replace(['/', '?'], ' ')->squish()->toString();
        $found     = [];

        foreach ($header as $index => $label) {
            $found[$normalise($label)] = $index;
        }

        $columns = [];

        foreach (self::EXPECTED_HEADERS as $key => $label) {
            $columns[$key] = $found[$normalise($label)] ?? null;
        }

        if ($columns['name'] === null) {
            throw new RuntimeException(
                'No "PRODUCT NAME" column in the header row — is this the right worksheet?'
            );
        }

        return $columns;
    }

    /**
     * @param  array{name:string,sku:?string}  $row
     * @return array{0: ?Product, 1: ?string}
     */
    private function match(array $row): array
    {
        if (filled($row['sku'])) {
            $bySku = Product::where('sku', $row['sku'])->first();

            if ($bySku) {
                return [$bySku, 'sku'];
            }
        }

        // Name is the only other identity the sheet carries. Compared with
        // whitespace collapsed and case ignored, because the same product
        // typed twice by two people differs by exactly that much.
        $byName = Product::whereRaw('LOWER(TRIM(name)) = ?', [Str::lower(trim($row['name']))])->first();

        return [$byName, $byName ? 'name' : null];
    }

    /**
     * What to write, leaving alone anything already set unless asked.
     *
     * A cost sheet is a starting point, not the authority: average_cost is
     * maintained from real receipts and a re-import must not stamp over
     * numbers the warehouse has since corrected.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function attributesFor(array $row, ?Product $product, bool $overwrite): array
    {
        $attributes = [];

        if (filled($row['sku']) && ($product === null || blank($product->sku) || $overwrite)) {
            $attributes['sku'] = $row['sku'];
        }

        // "box" and "pack" are how the sheet describes the form factor, which
        // is what product_type already holds. It is deliberately not
        // is_container: a booster box is a box of packs to a person, but a
        // container here is something with recorded contents to break into.
        if (filled($row['type']) && ($product === null || blank($product->product_type) || $overwrite)) {
            $attributes['product_type'] = Str::lower($row['type']);
        }

        if ($row['cost'] !== null && ($product === null || $product->unit_cost === null || $overwrite)) {
            $attributes['unit_cost'] = $row['cost'];
        }

        if ($row['sale_price'] !== null && ($product === null || $product->sale_price === null || $overwrite)) {
            $attributes['sale_price'] = $row['sale_price'];
        }

        // Auction / BIN / Both has no column of its own yet, and dropping it
        // on import would lose the only record of it. A note keeps it against
        // the product until it earns a field.
        if (filled($row['sold_as'])) {
            $note     = 'Sold as: ' . $row['sold_as'];
            $existing = (string) ($product?->notes ?? '');

            if (! str_contains($existing, $note)) {
                $attributes['notes'] = trim($existing === '' ? $note : $existing . "\n" . $note);
            }
        }

        return $attributes;
    }

    /**
     * Every change as field, before and after — the shape a preview table and a
     * console line both want.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<int, array{field:string,from:?string,to:string}>
     */
    public function changes(array $attributes, ?Product $product): array
    {
        $labels = [
            'sku'          => 'SKU',
            'product_type' => 'Type',
            'unit_cost'    => 'Unit cost',
            'sale_price'   => 'Sale target',
            'notes'        => 'Note',
        ];

        $changes = [];

        // Costs are read as floats, and "24.5" beside "189.99" in a column of
        // money reads as a different kind of number than it is.
        $money = fn ($value) => number_format((float) $value, 2);

        foreach ($attributes as $key => $value) {
            $from    = $product?->{$key};
            $isMoney = in_array($key, ['unit_cost', 'sale_price'], true);

            $changes[] = [
                'field' => $labels[$key] ?? $key,
                'from'  => $key === 'notes' || $from === null || $from === ''
                    ? null
                    : ($isMoney ? $money($from) : (string) $from),
                'to'    => $key === 'notes'
                    ? 'Sold-as note added'
                    : ($isMoney ? $money($value) : (string) $value),
            ];
        }

        return $changes;
    }

    private function text(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * Numbers only, and never a formula.
     *
     * The margin column in the sheet holds "=F2-E2" strings; a cost or target
     * cell could pick one up the same way, and (float) on that gives 0.00 —
     * a silent, plausible, wrong number.
     */
    private function money(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '' || str_starts_with($value, '=')) {
                return null;
            }

            $value = str_replace(['$', ','], '', $value);

            if (! is_numeric($value)) {
                return null;
            }
        }

        return round((float) $value, 2);
    }
}
