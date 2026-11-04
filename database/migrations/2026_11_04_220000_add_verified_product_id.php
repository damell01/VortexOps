<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('fulfillment_verifications', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('buyer_id')->constrained('products')->nullOnDelete();
            $table->index(['buyer_id', 'product_id', 'result'], 'fulfillment_verification_product_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('fulfillment_verifications', function (Blueprint $table) {
            $table->dropIndex('fulfillment_verification_product_lookup');
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
