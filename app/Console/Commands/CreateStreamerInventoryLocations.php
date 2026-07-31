<?php

namespace App\Console\Commands;

use App\Models\Streamer;
use App\Models\InventoryLocation;
use Illuminate\Console\Command;

class CreateStreamerInventoryLocations extends Command
{
    protected $signature = 'inventory:create-streamer-locations {--force}';

    protected $description = 'Create inventory locations for all streamers in the database';

    public function handle()
    {
        $streamers = Streamer::all();

        if ($streamers->isEmpty()) {
            $this->info('No streamers found in the database.');
            return Command::SUCCESS;
        }

        $this->info("Found {$streamers->count()} streamer(s).\n");

        if (!$this->option('force')) {
            $this->warn('Preview mode — use --force to actually create locations');
            $this->line('');
        }

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
                $this->line("⊘ <fg=yellow>Skipped:</> {$locationName} (already exists)");
                $skipped++;
                continue;
            }

            if ($this->option('force')) {
                InventoryLocation::create([
                    'name' => $locationName,
                    'type' => 'streamer_inventory',
                    'streamer_id' => $streamer->id,
                    'whatnot_channel_id' => $streamer->whatnot_channel_id,
                    'status' => 'active',
                    'notes' => "Auto-created inventory location for {$streamer->name}",
                ]);

                $this->line("✓ <fg=green>Created:</> {$locationName}");
                $created++;
            } else {
                $this->line("✓ Would create: {$locationName}");
                $created++;
            }
        }

        $this->line('');
        $this->info("Summary:");
        $this->line("  Created:  {$created}");
        $this->line("  Skipped:  {$skipped}");
        $this->line("  Total:    {$streamers->count()}");

        if (!$this->option('force')) {
            $this->line('');
            $this->info('Run with --force to create these locations:');
            $this->line('  php artisan inventory:create-streamer-locations --force');
        }

        return Command::SUCCESS;
    }
}
