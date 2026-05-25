<?php

namespace App\Services;

use App\Models\Show;
use App\Models\Streamer;
use Illuminate\Support\Facades\Log;

class AiTitleParserService
{
    public function __construct(private OllamaService $ollama) {}

    public function parse(Show $show): void
    {
        try {
            $streamers = Streamer::where('status', 'active')
                ->select('id', 'name')
                ->get()
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])
                ->all();

            $prompt = "A Whatnot sports card show has been created with the title: \"{$show->title}\". "
                . "Based on the title and the list of active streamers below, suggest which streamer(s) are likely running this show. "
                . "Respond ONLY with valid JSON: {\"suggestions\": [{\"streamer_id\": 1, \"streamer_name\": \"...\", \"confidence\": \"high|medium|low\", \"reason\": \"...\"}]}. "
                . "Active streamers: " . json_encode($streamers) . ". "
                . "If the title gives no useful signal, return an empty suggestions array.";

            $result = $this->ollama->json($prompt);

            if (empty($result['suggestions'])) {
                return;
            }

            $show->ai_streamer_suggestion = $result['suggestions'];
            $show->save();

            $autoAssign = \App\Models\Setting::getBool('auto_assign_confident_streamers', true);

            if ($autoAssign) {
                foreach ($result['suggestions'] as $suggestion) {
                    if (($suggestion['confidence'] ?? '') === 'high' && ! empty($suggestion['streamer_id'])) {
                        $show->streamers()->syncWithoutDetaching([
                            $suggestion['streamer_id'] => ['is_primary' => true],
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('AiTitleParserService failed', ['show_id' => $show->id, 'error' => $e->getMessage()]);
        }
    }
}
