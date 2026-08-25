<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give an ingestion row a channel of its own.
 *
 * "Which channel did this come from?" was answerable only by joining to the
 * show — and the rows that matter most are exactly the ones with no show:
 * a scrape that failed before it could identify one logs show_id null, so
 * every failure fell out of a per-channel view entirely.
 *
 * The scheduled importers were already writing the channel into raw_payload
 * as _channel_id, which is enough to backfill from but not to filter or
 * group by without digging through JSON on every row.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Adding the column and backfilling it are two statements, and the
        // second one failing leaves the first applied with nothing recorded
        // in the migrations table — so the retry has to be able to pick up
        // wherever the first attempt stopped.
        if (! Schema::hasColumn('show_ingestion_logs', 'whatnot_channel_id')) {
            Schema::table('show_ingestion_logs', function (Blueprint $table) {
                $table->foreignId('whatnot_channel_id')
                    ->nullable()
                    ->after('show_id')
                    ->constrained('whatnot_channels')
                    ->nullOnDelete();

                // The page reads "this channel, newest first" and little else.
                $table->index(['whatnot_channel_id', 'created_at']);
            });
        }

        $this->backfill();
    }

    /**
     * Two sources, in order of trust: the show's own channel where a show was
     * matched, then the _channel_id the scheduled importers stamp into the
     * payload. Rows with neither stay null — those are failures from before
     * the channel was known, and guessing would file them under a channel
     * that may not be the one that failed.
     *
     * Done in PHP rather than an UPDATE ... JOIN: SQLite has no such
     * statement, and the tests run on SQLite.
     */
    private function backfill(): void
    {
        $showChannels = DB::table('shows')
            ->whereNotNull('whatnot_channel_id')
            ->pluck('whatnot_channel_id', 'id');

        $validChannels = DB::table('whatnot_channels')->pluck('id')->flip();

        DB::table('show_ingestion_logs')
            ->whereNull('whatnot_channel_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($showChannels, $validChannels) {
                $updates = [];

                foreach ($rows as $row) {
                    $id = $row->show_id !== null
                        ? ($showChannels[$row->show_id] ?? null)
                        : null;

                    if ($id === null) {
                        $payload = json_decode($row->raw_payload ?? '', true);
                        $id      = is_array($payload) ? ($payload['_channel_id'] ?? null) : null;
                    }

                    // A channel deleted since the row was written would fail
                    // the foreign key and take the whole migration with it.
                    if ($id === null || ! $validChannels->has((int) $id)) {
                        continue;
                    }

                    $updates[(int) $id][] = $row->id;
                }

                // One statement per channel rather than per row: a busy log
                // table can hold a lot of rows and they share few channels.
                foreach ($updates as $channelId => $ids) {
                    DB::table('show_ingestion_logs')
                        ->whereIn('id', $ids)
                        ->update(['whatnot_channel_id' => $channelId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('show_ingestion_logs', function (Blueprint $table) {
            $table->dropIndex(['whatnot_channel_id', 'created_at']);
            $table->dropConstrainedForeignId('whatnot_channel_id');
        });
    }
};
