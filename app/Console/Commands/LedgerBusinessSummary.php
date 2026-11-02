<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LedgerBusinessSummary extends Command
{
    protected $signature = 'ledger:business-summary {--from= : Earliest transaction date YYYY-MM-DD} {--to= : Latest transaction date YYYY-MM-DD} {--json : Machine-readable output}';
    protected $description = 'Read-only financial ledger summary by approved business and category';

    public function handle(): int
    {
        foreach (['from', 'to'] as $option) {
            $value = $this->option($option);
            if ($value !== null && (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)
                || ! checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4)))) {
                $this->error('Invalid '.$option.' date; expected YYYY-MM-DD.');
                return self::FAILURE;
            }
        }

        $query = DB::table('ledger_attributions as a')
            ->join('whatnot_ledger_entries as e', 'e.id', '=', 'a.whatnot_ledger_entry_id')
            ->leftJoin('businesses as b', 'b.id', '=', 'a.business_id')
            ->where('a.status', 'approved')
            ->when($this->option('from'), fn ($q, $v) => $q->whereDate('e.created_date', '>=', $v))
            ->when($this->option('to'), fn ($q, $v) => $q->whereDate('e.created_date', '<=', $v))
            ->groupBy('a.business_id', 'b.name', 'a.target', 'a.category')
            ->select('a.business_id', 'b.name as business', 'a.target', 'a.category')
            ->selectRaw('COUNT(*) as transactions')
            ->selectRaw('SUM(e.amount) as net_amount')
            ->orderBy('b.name')->orderBy('a.category');

        $rows = $query->get()->map(fn ($row) => [
            'business' => $row->business ?? 'Unassigned business / house',
            'target' => $row->target,
            'category' => $row->category,
            'transactions' => (int) $row->transactions,
            'net_amount' => round((float) $row->net_amount, 2),
        ])->all();

        if ($this->option('json')) {
            $this->line(json_encode($rows, JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Business', 'Target', 'Category', 'Transactions', 'Net amount'],
                array_map(fn ($row) => array_values($row), $rows));
        }

        return self::SUCCESS;
    }
}
