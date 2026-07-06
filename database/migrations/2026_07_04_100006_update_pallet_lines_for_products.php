<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallet_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('pallet_lines', 'receiving_session_id'))
                $table->foreignId('receiving_session_id')->nullable()->after('pallet_id')
                    ->constrained('receiving_sessions')->nullOnDelete();
            if (! Schema::hasColumn('pallet_lines', 'vendor_description'))
                $table->string('vendor_description')->nullable()->after('description');
            if (! Schema::hasColumn('pallet_lines', 'match_confidence'))
                $table->decimal('match_confidence', 5, 4)->nullable()->after('inventory_item_id');
            if (! Schema::hasColumn('pallet_lines', 'match_stage'))
                $table->string('match_stage', 20)->nullable()->after('match_confidence');
            if (! Schema::hasColumn('pallet_lines', 'product_identity_id'))
                $table->foreignId('product_identity_id')->nullable()->after('match_stage')
                    ->constrained('product_identities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pallet_lines', function (Blueprint $table) {
            $table->dropForeign(['receiving_session_id']);
            $table->dropForeign(['product_identity_id']);
            $table->dropColumn(['receiving_session_id', 'vendor_description', 'match_confidence', 'match_stage', 'product_identity_id']);
        });
    }
};
