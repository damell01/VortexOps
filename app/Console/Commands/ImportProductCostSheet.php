<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Bring the product cost sheet into the catalogue.
 *
 * The costs, targets and product names lived in a spreadsheet that predates
 * this app, and re-typing 158 rows into a form is both slow and a good way to
 * introduce a typo into a number that decides what a break is worth.
 *
 * Matching is by SKU where the sheet has one and by name otherwise, so the
 * command can be run twice without duplicating the catalogue — the second run
 * updates what the first created.
 */
class ImportProductCostSheet extends Command
{
    protected $signature = 'inventory:import-cost-sheet
                            {file : Path to the .xlsx workbook}
                            {--sheet=Product cost ref sheet : Worksheet to read}
                            {--dry-run : Report what would change and write nothing}
                            {--overwrite-prices : Replace costs and targets on items that already have them}';

    protected $description = 'Import products, costs and sale targets from the product cost reference sheet';

    /** Header text → the column letter it was found in. */
    private const EXPECTED_HEADERS = [
        'name'       => 'PRODUCT NAME',
        'sku'        => 'SKU',
        'type'       => 'Type',
        'sold_as'    => 'Auction or BIN?',
        'cost'       => 'Cost',
        'sale_price' => 'Sale price / Target',
    ];

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! is_file($path)) {
            $this->error("No file at {$path}");

            return self::FAILURE;
        }

        try {
            $rows = $this->read($path, (string) $this->option('sheet'));
        } catch (\Throwable $e) {
            $this->error('Could not read the workbook: ' . $e->getMessage());

            return self::FAILURE;
        }

        if ($rows === []) {
            $this->warn('No named rows found — nothing to import.');

            return self::SUCCESS;
        }

        $dryRun    = (bool) $this->option('dry-run');
        $overwrite = (bool) $this->option('overwrite-prices');

        $created = $updated = $unchanged = 0;
        $changes = [];

        foreach ($rows as $row) {
            $product = $this->match($row);
            $isNew   = $product === null;

            $attributes = $this->attributesFor($row, $product, $overwrite);

            if (! $isNew && $attributes === []) {
                $unchanged++;
                continue;
            }

            $changes[] = [
                $isNew ? 'create' : 'update',
                Str::limit($row['name'], 46),
                $this->describe($attributes, $product),
            ];

            if ($dryRun) {
                $isNew ? $created++ : $updated++;
                continue;
            }

            if ($isNew) {
                Product::create($attributes + ['name' => $row['name'], 'is_active' => true]);
                $created++;
            } else {
                $product->fill($attributes)->save();
                $updated++;
            }
        }

        $this->newLine();

        if ($changes !== []) {
            $this->table(['', 'Product', 'What changes'], array_slice($changes, 0, 60));

            if (count($changes) > 60) {
                $this->line('  <fg=gray>… and ' . (count($changes) - 60) . ' more</>');
            }
        }

        $this->newLine();
        $this->line(sprintf(
            '  <fg=green>%d created</>   <fg=yellow>%d updated</>   <fg=gray>%d already matched</>',
            $created,
            $updated,
            $unchanged,
        ));

        if ($dryRun) {
            $this->newLine();
            $this->warn('Dry run — nothing was written. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{name:string,sku:?string,type:?string,sold_as:?string,cost:?float,sale_price:?float}>
     */
    private function read(string $path, string $sheet): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([$sheet]);

        $worksheet = $reader->load($path)->getSheet(0);
        $raw       = $worksheet->toArray(null, false, false, false);

        if ($raw === []) {
            return [];
        }

        $columns = $this->mapHeaders(array_shift($raw));

        $rows = [];

        foreach ($raw as $line) {
            $name = trim((string) ($line[$columns['name']] ?? ''));

            // The sheet runs on for hundreds of empty rows past the last
            // product; a blank name is the only reliable end marker.
            if ($name === '') {
                continue;
            }

            $rows[] = [
                'name'       => $name,
                'sku'        => $this->text($line[$columns['sku']] ?? null),
                'type'       => $this->text($line[$columns['type']] ?? null),
                'sold_as'    => $this->text($line[$columns['sold_as']] ?? null),
                'cost'       => $this->money($line[$columns['cost']] ?? null),
                'sale_price' => $this->money($line[$columns['sale_price']] ?? null),
            ];
        }

        return $rows;
    }

    /**
     * Find each expected header, wherever it sits.
     *
     * Positional reading breaks silently the first time someone inserts a
     * column, and the failure looks like a data problem rather than a layout
     * one — costs landing in the name field, or every price reading null.
     *
     * @param  array<int, mixed>  $header
     * @return array<string, int>
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
            throw new \RuntimeException(
                'No "PRODUCT NAME" column in the header row — is this the right worksheet?'
            );
        }

        return $columns;
    }

    /**
     * @param  array{name:string,sku:?string}  $row
     */
    private function match(array $row): ?Product
    {
        if (filled($row['sku'])) {
            $bySku = Product::where('sku', $row['sku'])->first();

            if ($bySku) {
                return $bySku;
            }
        }

        // Name is the only other identity the sheet carries. Compared with
        // whitespace collapsed and case ignored, because the same product
        // typed twice by two people differs by exactly that much.
        return Product::whereRaw('LOWER(TRIM(name)) = ?', [Str::lower(trim($row['name']))])->first();
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
            $note = 'Sold as: ' . $row['sold_as'];
            $existing = (string) ($product?->notes ?? '');

            if (! str_contains($existing, $note)) {
                $attributes['notes'] = trim($existing === '' ? $note : $existing . "\n" . $note);
            }
        }

        return $attributes;
    }

    /** @param array<string, mixed> $attributes */
    private function describe(array $attributes, ?Product $product): string
    {
        if ($attributes === []) {
            return '—';
        }

        $parts = [];

        foreach ($attributes as $key => $value) {
            $label = match ($key) {
                'unit_cost'    => 'cost',
                'sale_price'   => 'target',
                'product_type' => 'type',
                'notes'        => 'note',
                default        => $key,
            };

            $parts[] = $key === 'notes'
                ? $label
                : $label . ' ' . ($product?->{$key} !== null ? $product->{$key} . ' → ' : '') . $value;
        }

        return implode(', ', $parts);
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
