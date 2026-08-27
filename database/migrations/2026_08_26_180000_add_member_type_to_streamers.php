<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fulfillment people need the same pay terms streamers already have.
 *
 * Everything required to pay somebody — payout type, percentage, package,
 * hourly, PWE and label rates, burden, cadence, ADP id — is already on
 * `streamers`, and the whole payout pipeline is keyed on streamer_id. A second
 * table would mean a polymorphic payee and a parallel copy of every one of
 * those rules, which is how two ways of computing the same pay end up
 * disagreeing.
 *
 * So the row stays; only what the person does is new. "Both" is a real case:
 * somebody who streams and also packs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('streamers', function (Blueprint $table) {
            if (! Schema::hasColumn('streamers', 'member_type')) {
                // Plain string, not an enum: adding a fourth kind of team member
                // should be a code change, not a migration on a locked column.
                $table->string('member_type')->default('streamer')->after('status')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('streamers', function (Blueprint $table) {
            if (Schema::hasColumn('streamers', 'member_type')) {
                $table->dropColumn('member_type');
            }
        });
    }
};
