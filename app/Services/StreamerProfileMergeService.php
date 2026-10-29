<?php

namespace App\Services;

use App\Models\Streamer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Consolidate duplicate streamer identities without deleting historical records.
 * Runs atomically. A conflict stops the entire merge instead of losing data.
 */
final class StreamerProfileMergeService
{
    private const TABLES = [
        'show_streamer', 'streamer_aliases', 'streamer_log_entries',
        'payouts', 'streamer_loans', 'profit_share_packets',
        'inventory_locations',
    ];

    public function preview(Streamer $source, Streamer $target): array
    {
        $counts = [];
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'streamer_id')) {
                $counts[$table] = DB::table($table)->where('streamer_id', $source->id)->count();
            }
        }
        return $counts;
    }

    public function merge(int $sourceId, int $targetId): array
    {
        if ($sourceId === $targetId) {
            throw ValidationException::withMessages(['target_id' => 'Select a different profile.']);
        }

        return DB::transaction(function () use ($sourceId, $targetId): array {
            $profiles = Streamer::query()->whereIn('id', [$sourceId, $targetId])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $profiles->get($sourceId);
            $target = $profiles->get($targetId);
            if (! $source || ! $target || ! $source->isStreamer() || ! $target->isStreamer()
                || $source->status !== 'active' || $target->status !== 'active'
                || (int) $source->business_id !== (int) $target->business_id) {
                throw ValidationException::withMessages(['target_id' => 'Both profiles must be active streamers in the same business.']);
            }
            if ($source->user_id && $target->user_id && $source->user_id !== $target->user_id) {
                throw ValidationException::withMessages(['target_id' => 'Both profiles have different login accounts. Reconcile those accounts before merging.']);
            }
            // Do not rewrite the ownership of locked/finalized historical payroll.
            if (Schema::hasTable('payouts') && Schema::hasColumn('payouts', 'streamer_id')) {
                $locked = DB::table('payouts')->where('streamer_id', $sourceId)
                    ->whereNotIn('status', ['draft', 'pending'])->exists();
                if ($locked) {
                    throw ValidationException::withMessages(['target_id' => 'The duplicate has finalized or historical payouts. Reconcile these before a full merge; no records were changed.']);
                }
            }

            $counts = $this->preview($source, $target);
            if (Schema::hasTable('show_streamer')) {
                $rows = DB::table('show_streamer')->where('streamer_id', $sourceId)->lockForUpdate()->get();
                foreach ($rows as $row) {
                    $existing = DB::table('show_streamer')->where('show_id', $row->show_id)
                        ->where('streamer_id', $targetId)->first();
                    if ($existing) {
                        if ($row->is_primary && ! $existing->is_primary) {
                            DB::table('show_streamer')->where('show_id', $row->show_id)
                                ->where('streamer_id', $targetId)->update(['is_primary' => true, 'updated_at' => now()]);
                        }
                        DB::table('show_streamer')->where('show_id', $row->show_id)
                            ->where('streamer_id', $sourceId)->delete();
                    } else {
                        DB::table('show_streamer')->where('show_id', $row->show_id)
                            ->where('streamer_id', $sourceId)->update(['streamer_id' => $targetId, 'updated_at' => now()]);
                    }
                }
            }
            foreach (self::TABLES as $table) {
                if ($table === 'show_streamer' || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'streamer_id')) continue;
                if ($table === 'streamer_log_entries' && Schema::hasColumn($table, 'show_id')) {
                    $conflict = DB::table($table.' as a')->join($table.' as b', 'a.show_id', '=', 'b.show_id')
                        ->where('a.streamer_id', $sourceId)->where('b.streamer_id', $targetId)->exists();
                    if ($conflict) throw ValidationException::withMessages(['target_id' => 'Both profiles have reports for the same show. Resolve that conflict before merging.']);
                }
                if ($table === 'streamer_aliases' && Schema::hasColumn($table, 'normalized_alias')) {
                    $conflict = DB::table($table.' as a')->join($table.' as b', 'a.normalized_alias', '=', 'b.normalized_alias')
                        ->where('a.streamer_id', $sourceId)->where('b.streamer_id', $targetId)->exists();
                    if ($conflict) throw ValidationException::withMessages(['target_id' => 'Duplicate aliases exist on both profiles. Resolve those before merging.']);
                }
                DB::table($table)->where('streamer_id', $sourceId)->update(['streamer_id' => $targetId]);
            }
            if (! $target->user_id && $source->user_id) {
                $target->update(['user_id' => $source->user_id]);
                $source->update(['user_id' => null]);
            }
            $source->update(['status' => 'inactive']);
            activity('streamer_profile_merge')->causedBy(auth()->user())->performedOn($source)
                ->withProperties(['source_id' => $sourceId, 'target_id' => $targetId, 'transferred' => $counts])
                ->log('Streamer profiles consolidated');
            return $counts;
        });
    }
}
