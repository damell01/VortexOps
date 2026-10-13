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

    /** What a file with only one sheet — a CSV — is called in the picker. */
    public const SINGLE_SHEET = 'The whole file';

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

        // A CSV is one sheet by definition and its reader has no worksheet list
        // to offer — only Xlsx, Xls, Ods, Xml and Gnumeric implement that. The
        // call was a fatal, which the import screen reported as "that file
        // could not be opened", pointing the blame at a perfectly good file.
        if (! $this->hasWorksheets($reader)) {
            return [self::SINGLE_SHEET];
        }

        return array_values($reader->listWorksheetNames($path));
    }

    /** Whether this reader's format can hold more than one worksheet. */
    private function hasWorksheets(object $reader): bool
    {
        return method_exists($reader, 'listWorksheetNames');
    }

    /**
     * Rows from one worksheet, cleaned but not yet matched against anything.
     *
     * @return array<int, array{line:int,name:string,sku:?string,type:?string,sold_as:?string,cost:?float,sale_price:?float,warnings:array<int,string>}>
     */
    public function read(string $path, ?string $sheet = null): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        // Nothing to choose and nothing to validate in a single-sheet file, and
        // naming a sheet a CSV does not have would load no rows at all.
        if ($this->hasWorksheets($reader)) {
            $sheet     = $sheet ?: $this->guessSheet($path, $this->sheetNames($path));
            $available = $this->sheetNames($path);

            if (! in_array($sheet, $available, true)) {
                throw new RuntimeException(sprintf(
                    'This file has no worksheet called "%s". It has: %s.',
                    $sheet,
                    implode(', ', $available) ?: 'nothing readable',
                ));
            }

            $reader->setLoadSheetsOnly([$sheet]);
        }

        $raw = $reader->load($path)->getSheet(0)->toArray(null, false, false, false);

        if ($raw === []) {
            return [];
        }

        // The header row is wherever the headers are: a title or an
        // instruction line above them is common on a hand-kept sheet.
        [$headerIndex, $columns] = $this->locateHeader($raw);
        $raw = array_slice($raw, $headerIndex + 1, null, true);
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
                // +1 because people count rows from 1.
                'line'       => $offset + 1,
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
                'similar'    => $product === null && empty($row['force_new']) ? $this->similarProducts($row['name']) : [],
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
    /**
     * Find the header row in the first rows of the sheet and map its columns.
     *
     * @param  array<int, array<int, mixed>>  $raw
     * @return array{0:int,1:array<string,?int>}
     */
    private function locateHeader(array $raw): array
    {
        $best = null;

        foreach (array_slice($raw, 0, 25, true) as $index => $line) {
            $columns = $this->mapHeaders($line);
            $score   = count(array_filter($columns, fn ($c) => $c !== null));

            if ($columns['name'] !== null && $score >= 2) {
                return [$index, $columns];
            }

            if ($score > ($best[2] ?? 0)) {
                $best = [$index, $columns, $score];
            }
        }

        // The product list in the original workbook leaves its name column's
        // header blank: SKU, Type, Cost... sit beside an empty cell. When
        // the other headers are there, the names are the blank-headed column
        // just before them.
        if ($best && $best[2] >= 2 && $best[1]['cost'] !== null) {
            $header = $raw[$best[0]];
            $first  = min(array_filter($best[1], fn ($c) => $c !== null));

            for ($i = $first - 1; $i >= 0; $i--) {
                if (trim((string) ($header[$i] ?? '')) === '') {
                    $best[1]['name'] = $i;

                    return [$best[0], $best[1]];
                }
            }
        }

        $looksLikeCalculations = collect(array_slice($raw, 0, 25))->flatten()
            ->contains(fn ($v) => is_string($v) && preg_match('/number sold|profit share/i', $v));

        throw new RuntimeException($looksLikeCalculations
            ? 'This is the Calculations tab, not the product list. In Google Sheets use File → Download → Microsoft Excel (.xlsx) to get every tab, then pick "Product cost ref sheet".'
            : 'No product name column found. The sheet needs a "PRODUCT NAME" header, or SKU / Cost headers beside an unlabelled name column — is this the right worksheet?');
    }

    /** @return array<string, ?int> */
    private function mapHeaders(array $header): array
    {
        $normalise = fn ($v) => Str::of((string) $v)->lower()->replace(['/', '?', ':'], ' ')->squish()->toString();
        $found     = [];

        foreach ($header as $index => $label) {
            $key = $normalise($label);
            if ($key !== '' && ! isset($found[$key])) {
                $found[$key] = $index;
            }
        }

        $aliases = [
            'name'       => ['product name', 'product', 'name', 'item', 'item name'],
            'sold_as'    => ['auction or bin', 'auction bin', 'auction or bin', 'sold as'],
            'sale_price' => ['sale price target', 'sale price', 'target', 'target price'],
        ];

        $columns = [];

        foreach (self::EXPECTED_HEADERS as $key => $label) {
            $columns[$key] = $found[$normalise($label)] ?? null;
            foreach ($aliases[$key] ?? [] as $alias) {
                $columns[$key] ??= $found[$alias] ?? null;
            }
        }

        // "Auction or BIN?" is often cut short or reworded; any header that
        // starts with "auction" is that column.
        if ($columns['sold_as'] === null) {
            foreach ($found as $text => $index) {
                if (str_starts_with($text, 'auction')) {
                    $columns['sold_as'] = $index;
                    break;
                }
            }
        }

        return $columns;
    }

    /**
     * The worksheet to start on: the product list by name (ignoring case and
     * spacing), else the first sheet whose headers look like one.
     *
     * @param  array<int, string>  $sheets
     */
    public function guessSheet(string $path, array $sheets): ?string
    {
        foreach ($sheets as $name) {
            if (Str::of($name)->lower()->squish()->toString() === Str::lower(self::DEFAULT_SHEET)) {
                return $name;
            }
        }

        if (count($sheets) > 1) {
            foreach ($sheets as $name) {
                try {
                    $this->read($path, $name);

                    return $name;
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return $sheets[0] ?? null;
    }

    /** @var array<int, array{id:int,name:string,sku:?string,key:string,digits:string}>|null */
    private ?array $catalogue = null;

    /**
     * Existing products whose name is close to this one but not the same —
     * "Brillant fantasy" beside "Brilliant Fantasy". Numbers must agree, so
     * "Collection 12" is never offered as a copy of "Collection 13".
     *
     * @return array<int, array{id:int,name:string,sku:?string}>
     */
    public function similarProducts(string $name, int $limit = 3): array
    {
        $this->catalogue ??= Product::query()->get(['id', 'name', 'sku'])
            ->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku] + $this->fuzzyKey((string) $p->name))
            ->all();

        ['key' => $key, 'digits' => $digits] = $this->fuzzyKey($name);
        if ($key === '') {
            return [];
        }

        $hits = [];
        foreach ($this->catalogue as $candidate) {
            if ($candidate['digits'] !== $digits || $candidate['key'] === '') {
                continue;
            }
            $a = strlen($key);
            $b = strlen($candidate['key']);
            if (min($a, $b) / max($a, $b) < 0.6) {
                continue;
            }
            similar_text($key, $candidate['key'], $percent);
            $contains = min($a, $b) >= 8 && (str_contains($key, $candidate['key']) || str_contains($candidate['key'], $key));
            if ($percent >= 85 || $contains) {
                $hits[] = ['id' => $candidate['id'], 'name' => $candidate['name'], 'sku' => $candidate['sku'], 'score' => $contains ? max($percent, 90) : $percent];
            }
        }

        usort($hits, fn ($x, $y) => $y['score'] <=> $x['score']);

        return array_map(fn ($h) => ['id' => $h['id'], 'name' => $h['name'], 'sku' => $h['sku']], array_slice($hits, 0, $limit));
    }

    /** @return array{key:string,digits:string} */
    private function fuzzyKey(string $name): array
    {
        $text = Str::of($name)->lower()->replace('&', ' and ')->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
        preg_match_all('/\d+/', $text, $m);

        return ['key' => str_replace(' ', '', $text), 'digits' => implode('-', $m[0])];
    }

    /**
     * @param  array{name:string,sku:?string}  $row
     * @return array{0: ?Product, 1: ?string}
     */
    private function match(array $row): array
    {
        // The person chose: update this existing product, or make a new one
        // even though the name resembles something already there.
        if (! empty($row['product_id'])) {
            $chosen = Product::find($row['product_id']);

            if ($chosen) {
                return [$chosen, 'your choice'];
            }
        }

        if (! empty($row['force_new']) && blank($row['sku'])) {
            return [null, null];
        }

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

        if ($row['cost'] !== null && ($product === null || (float) $product->unit_cost <= 0 || $overwrite)) {
            $attributes['unit_cost'] = $row['cost'];
        }

        if ($row['sale_price'] !== null && ($product === null || (float) $product->sale_price <= 0 || $overwrite)) {
            $attributes['sale_price'] = $row['sale_price'];
        }

        // Auction / BIN / Both used to be appended to the notes field because
        // there was nowhere else for it. It has its own column now, so it is
        // filterable and reportable rather than a sentence in a text box.
        if (filled($row['sold_as']) && ($product === null || blank($product->sold_as) || $overwrite)) {
            $attributes['sold_as'] = $this->soldAs($row['sold_as']);
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
            'sold_as'      => 'Sold as',
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

    /**
     * Tidy the sheet's spelling of Auction / BIN / Both without rejecting a
     * value nobody here has seen. A vendor writing "buy it now" should land on
     * the same value as one writing "BIN"; a vendor writing something else
     * entirely is kept as typed rather than dropped.
     */
    private function soldAs(string $value): string
    {
        $normalised = Str::of($value)->lower()->replace(['-', '_', '/'], ' ')->squish()->toString();

        return match (true) {
            in_array($normalised, ['auction', 'auctions', 'auction only'], true)       => 'Auction',
            in_array($normalised, ['bin', 'b i n', 'buy it now', 'buynow'], true)      => 'BIN',
            in_array($normalised, ['both', 'either', 'auction bin', 'bin auction'], true) => 'Both',
            default                                                                    => trim($value),
        };
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
