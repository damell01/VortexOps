<?php

namespace App\Console\Commands;

use App\Services\ProductSheetImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Bring the product cost sheet into the catalogue, from a terminal.
 *
 * The reading, matching and writing live in ProductSheetImporter, shared with
 * the Import Inventory Sheet screen. This command is the terminal's view of
 * that service and nothing more — two implementations would mean the preview
 * on screen was a preview of different behaviour.
 */
class ImportProductCostSheet extends Command
{
    protected $signature = 'inventory:import-cost-sheet
                            {file : Path to the .xlsx workbook}
                            {--sheet= : Worksheet to read}
                            {--dry-run : Report what would change and write nothing}
                            {--overwrite-prices : Replace costs and targets on items that already have them}';

    protected $description = 'Import products, costs and sale targets from the product cost reference sheet';

    public function handle(ProductSheetImporter $importer): int
    {
        $path = $this->argument('file');

        if (! is_file($path)) {
            $this->error("No file at {$path}");

            return self::FAILURE;
        }

        try {
            $rows = $importer->read($path, $this->option('sheet') ?: null);
        } catch (\Throwable $e) {
            $this->error('Could not read the workbook: ' . $e->getMessage());

            return self::FAILURE;
        }

        if ($rows === []) {
            $this->warn('No named rows found — nothing to import.');

            return self::SUCCESS;
        }

        $overwrite = (bool) $this->option('overwrite-prices');
        $plan      = $importer->plan($rows, $overwrite);

        $lines = [];

        foreach ($plan['rows'] as $row) {
            if ($row['action'] === 'unchanged') {
                continue;
            }

            $lines[] = [
                $row['action'],
                Str::limit($row['name'], 46),
                $this->describe($row['changes']),
            ];
        }

        $this->newLine();

        if ($lines !== []) {
            $this->table(['', 'Product', 'What changes'], array_slice($lines, 0, 60));

            if (count($lines) > 60) {
                $this->line('  <fg=gray>… and ' . (count($lines) - 60) . ' more</>');
            }
        }

        foreach ($plan['rows'] as $row) {
            foreach ($row['warnings'] as $warning) {
                $this->line("  <fg=yellow>line {$row['line']}:</> {$warning}");
            }
        }

        if ((bool) $this->option('dry-run')) {
            $this->newLine();
            $this->line(sprintf(
                '  <fg=green>%d would be created</>   <fg=yellow>%d would be updated</>   <fg=gray>%d already matched</>',
                $plan['summary']['create'],
                $plan['summary']['update'],
                $plan['summary']['unchanged'],
            ));
            $this->newLine();
            $this->warn('Dry run — nothing was written. Re-run without --dry-run to apply.');

            return self::SUCCESS;
        }

        $result = $importer->apply($rows, $overwrite);

        $this->newLine();
        $this->line(sprintf(
            '  <fg=green>%d created</>   <fg=yellow>%d updated</>   <fg=gray>%d already matched</>',
            $result['created'],
            $result['updated'],
            $result['unchanged'],
        ));

        return self::SUCCESS;
    }

    /** @param array<int, array{field:string,from:?string,to:string}> $changes */
    private function describe(array $changes): string
    {
        if ($changes === []) {
            return '—';
        }

        return implode(', ', array_map(
            fn (array $c) => Str::lower($c['field']) . ' ' . ($c['from'] !== null ? $c['from'] . ' → ' : '') . $c['to'],
            $changes,
        ));
    }
}
