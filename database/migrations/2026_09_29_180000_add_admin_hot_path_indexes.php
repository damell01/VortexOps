<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hot admin reads are channel-scoped and then sorted/filtered by date.
        // Existing indexes cover these columns individually or in the reverse
        // order; this ordering lets MySQL seek one channel's date window.
        $this->addIndex('shows', ['whatnot_channel_id', 'show_date'], 'shows_channel_date_idx');
        $this->addIndex('shows', ['whatnot_channel_id', 'status', 'show_date'], 'shows_channel_status_date_idx');

        // Inventory overview/recent-receiving pages are newest-first and scoped.
        $this->addIndex('inventory_movements', ['created_at'], 'inventory_movements_created_idx');
        if (Schema::hasColumn('inventory_movements', 'channel_id')) {
            $this->addIndex('inventory_movements', ['channel_id', 'created_at'], 'inventory_movements_channel_created_idx');
        }

        // Payout lists repeatedly filter status and then show newest records.
        $this->addIndex('payouts', ['status', 'created_at'], 'payouts_status_created_idx');
    }

    public function down(): void
    {
        $this->dropIndex('shows', 'shows_channel_date_idx');
        $this->dropIndex('shows', 'shows_channel_status_date_idx');
        $this->dropIndex('inventory_movements', 'inventory_movements_created_idx');
        $this->dropIndex('inventory_movements', 'inventory_movements_channel_created_idx');
        $this->dropIndex('payouts', 'payouts_status_created_idx');
    }

    private function addIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table)) return;
        foreach ($columns as $column) if (! Schema::hasColumn($table, $column)) return;
        try { Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name)); } catch (\Throwable) {}
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! Schema::hasTable($table)) return;
        try { Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name)); } catch (\Throwable) {}
    }
};
