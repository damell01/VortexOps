<?php

namespace App\Jobs;

use App\Models\Show;
use App\Services\AiTitleParserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ParseShowTitle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $showId) {}

    public function handle(AiTitleParserService $service): void
    {
        $show = Show::find($this->showId);

        if (! $show) {
            Log::warning('ParseShowTitle: show not found', ['show_id' => $this->showId]);
            return;
        }

        $service->parse($show);
    }
}
