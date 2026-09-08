<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use App\Models\Show;
use App\Models\StreamerLogEntry;
use App\Models\StreamerLogItem;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use Illuminate\Database\Seeder;

class HandbookScreenshotSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'dev@vortexbreaks.com')->firstOrFail();
        $shows = Show::query()->orderBy('id')->take(2)->get();

        if ($shows->count() < 2) {
            throw new \RuntimeException('Handbook screenshots require at least two seeded shows.');
        }

        $fulfillmentShow = $shows->first();
        $reviewShow = $shows->get(1);
        $fulfillmentStreamer = $fulfillmentShow->primaryStreamer() ?? $fulfillmentShow->streamers()->first();
        $reviewStreamer = $reviewShow->primaryStreamer() ?? $reviewShow->streamers()->first();

        if (! $fulfillmentStreamer || ! $reviewStreamer) {
            throw new \RuntimeException('Handbook screenshots require streamers on the first two seeded shows.');
        }

        $fulfillmentLog = StreamerLogEntry::updateOrCreate(
            ['show_id' => $fulfillmentShow->id, 'streamer_id' => $fulfillmentStreamer->id],
            [
                'status' => 'admin_approved',
                'approval_status' => 'approved',
                'gross_revenue' => $fulfillmentShow->gross_revenue,
                'product_cost' => 250,
                'hours_streamed' => 3.5,
                'number_of_shipments' => 6,
                'submitted_at' => now()->subHours(3),
                'streamer_reviewed_at' => now()->subHours(2),
                'reviewed_by' => $admin->id,
                'reviewed_at' => now()->subHour(),
                'locked_at' => now()->subHour(),
                'fulfillment_reviewed_at' => null,
                'fulfillment_reviewed_by' => null,
            ]
        );

        $inventory = InventoryItem::query()->where('is_active', true)->first() ?? InventoryItem::query()->first();
        if (! $inventory) {
            throw new \RuntimeException('Handbook screenshots require at least one inventory item.');
        }

        StreamerLogItem::updateOrCreate(
            ['streamer_log_entry_id' => $fulfillmentLog->id, 'inventory_item_id' => $inventory->id],
            [
                'item_name' => $inventory->name,
                'quantity' => 3,
                'packed_quantity' => 1,
                'unit_cost' => (float) ($inventory->average_cost ?? 25),
                'disposition' => 'sold',
                'fulfillment_status' => StreamerLogItem::FULFILLMENT_PENDING,
            ]
        );

        $fulfillmentShow->fulfillmentUsers()->syncWithoutDetaching([$admin->id]);

        $reviewLog = StreamerLogEntry::updateOrCreate(
            ['show_id' => $reviewShow->id, 'streamer_id' => $reviewStreamer->id],
            [
                'status' => 'streamer_reviewed',
                'approval_status' => 'pending_approval',
                'gross_revenue' => $reviewShow->gross_revenue,
                'product_cost' => 180,
                'hours_streamed' => 2.75,
                'number_of_shipments' => 5,
                'submitted_at' => now()->subMinutes(45),
                'streamer_reviewed_at' => now()->subMinutes(40),
                'approval_requested_at' => now()->subMinutes(40),
                'locked_at' => null,
            ]
        );

        StreamerLogItem::updateOrCreate(
            ['streamer_log_entry_id' => $reviewLog->id, 'inventory_item_id' => $inventory->id],
            [
                'item_name' => $inventory->name,
                'quantity' => 2,
                'packed_quantity' => 0,
                'unit_cost' => (float) ($inventory->average_cost ?? 25),
                'disposition' => 'sold',
                'fulfillment_status' => StreamerLogItem::FULFILLMENT_PENDING,
            ]
        );

        $payRun = WeeklyPayoutBatch::query()->orderBy('id')->first();
        if (! $payRun) {
            throw new \RuntimeException('Handbook screenshots require at least one pay run.');
        }

        $fixtures = [
            'show_id' => $fulfillmentShow->id,
            'review_log_id' => $reviewLog->id,
            'fulfillment_show_id' => $fulfillmentShow->id,
            'pay_run_id' => $payRun->id,
        ];

        file_put_contents(
            storage_path('handbook-screenshot-fixtures.json'),
            json_encode($fixtures, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );
    }
}
