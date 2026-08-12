<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Dual sales capture: Whatnot final totals + paper sheet submitted by streamer.
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->decimal('paper_sales_gross', 10, 2)->nullable()->after('tips');
            $table->unsignedInteger('paper_sales_units')->nullable()->after('paper_sales_gross');
            $table->text('paper_sales_notes')->nullable()->after('paper_sales_units');
            $table->boolean('sales_reconciled')->default(false)->after('paper_sales_notes');
        });
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->dropColumn(['paper_sales_gross', 'paper_sales_units', 'paper_sales_notes', 'sales_reconciled']);
        });
    }
};
