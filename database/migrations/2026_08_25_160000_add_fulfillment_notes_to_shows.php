<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A heads-up for whoever packs the show.
 *
 * Nothing carried from the person who ran the stream to the people who ship
 * it. "This one is all big boxes" or "hold the Smith order, buyer is
 * combining" was said out loud on the night and gone by morning, so
 * fulfillment met it as a surprise in the queue.
 *
 * The flag is separate from the note on purpose: a note has to be read, and
 * the thing worth knowing before you plan an afternoon is whether this show
 * is going to eat one. The flag can be sorted and filtered; the note says
 * why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->text('fulfillment_notes')->nullable()->after('notes');
            $table->boolean('is_slow_pack')->default(false)->after('fulfillment_notes');

            // The fulfillment queue's whole job is "what needs doing next",
            // so slow shows are filtered on constantly.
            $table->index(['is_slow_pack', 'show_date']);
        });
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->dropIndex(['is_slow_pack', 'show_date']);
            $table->dropColumn(['fulfillment_notes', 'is_slow_pack']);
        });
    }
};
