<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The businesses payroll is run for. Both sell on the same Whatnot channels,
 * so a show's business is its streamer's: each team member belongs to one.
 * Pay runs carry the business they were created for, so one business's run
 * can be finalized or marked paid without touching the other's people.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name', 20)->nullable();
            $table->string('color', 20)->default('#7c3aed');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('streamers', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->after('whatnot_channel_id')->constrained('businesses')->nullOnDelete();
            $table->decimal('house_cut_percentage', 5, 2)->nullable()->after('payout_percentage');
            $table->string('sales_split_basis', 10)->nullable()->after('house_cut_percentage');
        });

        Schema::table('weekly_payout_batches', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->after('id')->constrained('businesses')->nullOnDelete();
        });

        $now = now();
        DB::table('businesses')->insert([
            ['name' => 'Vortex Collects', 'short_name' => 'VCX', 'color' => '#7c3aed', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Estill Distribution', 'short_name' => 'Estill', 'color' => '#0d9488', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->releaseForcedPayType();
    }

    /**
     * Saving a streamer's pay (the Pay section, an individual override) used
     * to store "profit share" as their own pay type every time, whether or
     * not anyone chose it. With one team default that was harmless; now a
     * business can pay its streamers another way, and that stored copy would
     * keep someone on profit share after they're moved into it. Where it only
     * repeats the shared default, it's dropped so the person follows their
     * business's default again.
     */
    private function releaseForcedPayType(): void
    {
        $raw = DB::table('settings')->where('key', 'payment_structure_streamer')->value('value');
        $defaults = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        if (! in_array($defaults['payout_type'] ?? null, [null, 'profit_share'], true)
            || ! in_array($defaults['payout_cadence'] ?? null, [null, 'weekly'], true)) {
            return;
        }

        DB::table('streamers')
            ->where(fn ($q) => $q->where('member_type', 'streamer')->orWhereNull('member_type'))
            ->whereNotNull('compensation_override_fields')
            ->where('payout_type', 'profit_share')
            ->orderBy('id')
            ->get(['id', 'compensation_override_fields', 'payout_cadence'])
            ->each(function ($row) {
                $fields = json_decode((string) $row->compensation_override_fields, true);
                if (! is_array($fields) || ! in_array('payout_type', $fields, true)) {
                    return;
                }
                $drop = ['payout_type'];
                if (in_array($row->payout_cadence, [null, 'weekly'], true)) {
                    $drop[] = 'payout_cadence';
                }
                DB::table('streamers')->where('id', $row->id)->update([
                    'compensation_override_fields' => json_encode(array_values(array_diff($fields, $drop))),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('weekly_payout_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_id');
        });
        Schema::table('streamers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_id');
            $table->dropColumn(['house_cut_percentage', 'sales_split_basis']);
        });
        Schema::dropIfExists('businesses');
    }
};
