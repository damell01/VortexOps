<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatnot_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatnot_channel_id')->nullable()->constrained('whatnot_channels')->nullOnDelete();

            // Faithful copy of a Whatnot ledger row (/dashboard/ledger/overview).
            $table->dateTime('created_date')->nullable();
            $table->dateTime('completed_date')->nullable();
            $table->decimal('amount', 12, 2)->nullable();           // signed: negative = deduction/charge
            $table->string('listing_id')->nullable();
            $table->string('whatnot_order_id')->nullable();          // numeric order id shown in the row
            $table->string('order_hash')->nullable();                // hash in /dashboard/orders/<hash>
            $table->text('message')->nullable();
            $table->string('status')->nullable();                    // Completed / Processing / …
            $table->string('transaction_type')->nullable();          // Sales / Withdrawal / …

            // Stable dedup key derived from the row's identifying fields.
            $table->string('dedup_key')->nullable()->unique();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->index(['whatnot_channel_id', 'created_date']);
            $table->index('whatnot_order_id');
            $table->index('transaction_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatnot_ledger_entries');
    }
};
