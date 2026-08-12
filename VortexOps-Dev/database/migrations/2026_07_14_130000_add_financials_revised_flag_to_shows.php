<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            // Set when a later Whatnot sync brings back different core financial
            // numbers (gross revenue, net, tips, units) for a show that's already
            // past pending_review — i.e. after mapping/payouts may have started
            // using the old figures. Surfaced for review, never silently ignored.
            if (! Schema::hasColumn('shows', 'financials_revised_after_lock')) {
                $table->boolean('financials_revised_after_lock')->default(false)->after('channel_attribution_suspect');
            }
            if (! Schema::hasColumn('shows', 'revision_notes')) {
                $table->text('revision_notes')->nullable()->after('financials_revised_after_lock');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            foreach (['financials_revised_after_lock', 'revision_notes'] as $column) {
                if (Schema::hasColumn('shows', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
