<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // shows.show_date — used in KPI widget whereBetween and list default sort
        Schema::table('shows', function (Blueprint $table) {
            $table->index('show_date', 'shows_show_date_idx');
        });

        // inventory_movements.created_at — RecentMovementsWidget ->latest() on a
        // large audit table is a full scan without this index
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->index('created_at', 'imovements_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('shows', fn (Blueprint $t) => $t->dropIndex('shows_show_date_idx'));
        Schema::table('inventory_movements', fn (Blueprint $t) => $t->dropIndex('imovements_created_at_idx'));
    }
};
