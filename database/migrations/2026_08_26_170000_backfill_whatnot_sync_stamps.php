<?php

use App\Models\Show;
use Illuminate\Database\Migrations\Migration;

/**
 * Record the fetches that already happened.
 *
 * whatnot:sync-show-index has been pulling analytics and shipments every ten
 * minutes for months, and writing the timestamp only into the raw_import_payload
 * JSON — never onto last_analytics_synced_at / last_shipments_synced_at. So
 * everything that reads those columns believed the work had never been done:
 * whatnot:refresh-recent kept re-selecting shows whose figures were already in,
 * and the backfill reported 567 shows outstanding on a channel of 570 while
 * their numbers sat in the same row.
 *
 * The JSON already holds the truth. This copies it onto the columns so the
 * count means something, and the command now writes both going forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Show::query()
            ->whereNotNull('raw_import_payload')
            ->where(function ($query) {
                $query->whereNull('last_analytics_synced_at')
                    ->orWhereNull('last_shipments_synced_at');
            })
            // Chunked by id: the update changes the where-clause's own columns,
            // so paginating on them would skip rows.
            ->chunkById(500, function ($shows) {
                foreach ($shows as $show) {
                    $raw = is_array($show->raw_import_payload) ? $show->raw_import_payload : [];
                    $stamps = [];

                    if ($show->last_analytics_synced_at === null && ($at = $this->parse($raw['_analytics_synced_at'] ?? null))) {
                        $stamps['last_analytics_synced_at'] = $at;
                    }

                    if ($show->last_shipments_synced_at === null && ($at = $this->parse($raw['_shipments_synced_at'] ?? null))) {
                        $stamps['last_shipments_synced_at'] = $at;
                    }

                    if ($stamps !== []) {
                        // saveQuietly: this is bookkeeping about past fetches,
                        // not a change worth firing observers or revision
                        // tracking over.
                        $show->forceFill($stamps)->saveQuietly();
                    }
                }
            });
    }

    public function down(): void
    {
        // Deliberately irreversible. The columns are a record of fetches that
        // genuinely happened; clearing them would recreate the false backlog
        // this migration exists to remove.
    }

    private function parse(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
};
