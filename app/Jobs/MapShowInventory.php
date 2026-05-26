<?php

namespace App\Jobs;

use App\Models\Show;
use App\Services\AiInventoryMapperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MapShowInventory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly int $showId) {}

    public function handle(AiInventoryMapperService $service): void
    {
        $show = Show::find($this->showId);

        if (! $show) {
            Log::warning('MapShowInventory: show not found', ['show_id' => $this->showId]);
            return;
        }

        $service->map($show);
    }
}
