<?php

namespace Database\Seeders;

use App\Models\Streamer;
use App\Models\InventoryLocation;
use Illuminate\Database\Seeder;

class CreateStreamerInventoryLocationsSeeder extends Seeder
{
    public function run(): void
    {
        $streamers = Streamer::all();

        $this->command->info("Creating inventory locations for {$streamers->count()} streamer(s)...\n");

        $created = 0;
        $skipped = 0;

        foreach ($streamers as $streamer) {
            $locationName = "{$streamer->name} - Inventory";

            // Check if location already exists
            $existing = InventoryLocation::where('streamer_id', $streamer->id)
                ->where('type', 'streamer_inventory')
                ->where('name', $locationName)
                ->exists();

            if ($existing) {
                $this->command->line("⊘ <fg=yellow>Skipped:</> {$locationName}");
                $skipped++;
                continue;
            }

            InventoryLocation::create([
                'name' => $locationName,
                'type' => 'streamer_inventory',
                'streamer_id' => $streamer->id,
                'whatnot_channel_id' => $streamer->whatnot_channel_id,
                'status' => 'active',
                'notes' => "Inventory location for {$streamer->name}",
            ]);

            $this->command->line("✓ <fg=green>Created:</> {$locationName}");
            $created++;
        }

        $this->command->line('');
        $this->command->info("Complete: {$created} created, {$skipped} skipped, {$streamers->count()} total");
    }
}
