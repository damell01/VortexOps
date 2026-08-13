<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Line items a streamer logs as sold during a show.
 *
 * Previously these lived in whatnot_show_orders, which is shaped around
 * scraped Whatnot data (whatnot_order_id, buyer_username, lot_number,
 * whatnot_show_url). Now that items are entered by hand rather than pulled
 * from Whatnot, those columns would never be populated, so the line items get
 * their own table owned by the log entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('streamer_log_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('streamer_log_entry_id')
                ->constrained('streamer_log_entries')
                ->cascadeOnDelete();

            // Nullable so a streamer can log something not in the catalogue
            // yet; deduction skips these and the UI can flag them.
            // InventoryItem maps to `products`, not `inventory_items`.
            $table->foreignId('inventory_item_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();

            // Kept even when mapped, so the row still reads correctly if the
            // catalogue item is later renamed or removed.
            $table->string('item_name');

            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_cost', 10, 2)->nullable();

            // Which location the units came out of. Resolved at submission if
            // left null, but recording it makes the deduction auditable.
            $table->foreignId('inventory_location_id')
                ->nullable()
                ->constrained('inventory_locations')
                ->nullOnDelete();

            // What deduction actually took, so a later restore returns exactly
            // that and nothing more. Without it, restoring put back lines that
            // were never deducted (short stock / unmatched) and inflated stock.
            $table->unsignedInteger('deducted_quantity')->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['streamer_log_entry_id', 'inventory_item_id'], 'sli_entry_item_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streamer_log_items');
    }
};
