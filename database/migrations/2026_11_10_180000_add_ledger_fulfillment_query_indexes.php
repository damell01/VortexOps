<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes supporting ledger attribution filters and the fulfillment
 * schedule/dashboard. No data is modified.
 */
return new class extends Migration {
    public function up(): void
    {
        $this->add('whatnot_ledger_entries', ['created_date', 'id'], 'wnledger_created_id_idx');
        $this->add('ledger_attributions', ['status', 'whatnot_ledger_entry_id'], 'ledger_attr_status_entry_idx');
        $this->add('ledger_attributions', ['show_id', 'status'], 'ledger_attr_show_status_idx');
        $this->add('ledger_attributions', ['streamer_id', 'status'], 'ledger_attr_stream_status_idx');
        $this->add('fulfillment_shifts', ['user_id', 'starts_at', 'ends_at'], 'fulfill_shift_user_window_idx');
        $this->add('fulfillment_shifts', ['starts_at', 'ends_at'], 'fulfill_shift_window_idx');
        $this->add('fulfillment_flags', ['status', 'created_at'], 'fulfill_flags_status_date_idx');
        $this->add('fulfillment_events', ['created_at', 'id'], 'fulfill_events_date_id_idx');
        $this->add('fulfillment_run_packages', ['status', 'packed_at'], 'fulfill_pkg_status_packed_idx');
        $this->add('fulfillment_run_buyers', ['status', 'packed_at'], 'fulfill_buy_status_packed_idx');
    }

    public function down(): void
    {
        foreach ([
            'whatnot_ledger_entries' => ['wnledger_created_id_idx'],
            'ledger_attributions' => ['ledger_attr_status_entry_idx', 'ledger_attr_show_status_idx', 'ledger_attr_stream_status_idx'],
            'fulfillment_shifts' => ['fulfill_shift_user_window_idx', 'fulfill_shift_window_idx'],
            'fulfillment_flags' => ['fulfill_flags_status_date_idx'],
            'fulfillment_events' => ['fulfill_events_date_id_idx'],
            'fulfillment_run_packages' => ['fulfill_pkg_status_packed_idx'],
            'fulfillment_run_buyers' => ['fulfill_buy_status_packed_idx'],
        ] as $table => $names) {
            if (! Schema::hasTable($table)) continue;
            foreach ($names as $name) {
                try { Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name)); }
                catch (\Throwable) { /* Index absent on this installation. */ }
            }
        }
    }

    private function add(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table)) return;
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) return;
        }
        // A migration may run on installations where a manually added index
        // already exists; avoid failing the entire deployment in that case.
        try { Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name)); }
        catch (\Throwable $e) {
            if (! str_contains(strtolower($e->getMessage()), 'duplicate') &&
                ! str_contains(strtolower($e->getMessage()), 'already exists')) {
                throw $e;
            }
        }
    }
};
