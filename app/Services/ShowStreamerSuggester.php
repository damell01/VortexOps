<?php

namespace App\Services;

use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerAlias;
use Illuminate\Support\Collection;

/** Read-only suggestions: never call Show::detectStreamers(), which attaches profiles. */
class ShowStreamerSuggester
{
    private ?Collection $roster = null;

    public function suggest(Show $show): array
    {
        $name = app(ShowHostDetection::class)->name($show->title);
        $nameKey = StreamerAlias::normalize($name);
        $title = (string) $show->title;
        $candidates = [];
        $this->roster ??= Streamer::query()->streamers()->where('status', 'active')
            ->with('aliases:id,streamer_id,alias')->orderBy('name')->get(['id','name']);

        foreach ($this->roster as $streamer) {
            foreach (collect([$streamer->name])->merge($streamer->aliases->pluck('alias'))->filter()->unique() as $identity) {
                $identityKey = StreamerAlias::normalize($identity);
                $exactHost = $name !== null && (
                    mb_strtolower(trim($identity)) === mb_strtolower(trim($name))
                    || (mb_strlen($nameKey) >= 3 && ! str_contains($identity, '$')
                        && ! str_contains($name, '$') && $identityKey === $nameKey)
                );
                $wholeIdentity = preg_match('/(?<![\p{L}\p{N}])'.preg_quote($identity,'/').'(?![\p{L}\p{N}])/iu', $title) === 1;
                if (! $exactHost && ! $wholeIdentity) continue;
                $candidates[$streamer->id] = [
                    'id'=>$streamer->id, 'name'=>$streamer->name,
                    'confidence'=>$exactHost ? 'high' : 'review',
                    'reason'=>$exactHost ? 'The detected host matches this profile or alias.' : 'This name or alias appears in the title. Check the host before assigning.',
                ];
                break;
            }
        }

        // Multiple candidates always remain a manual choice, including two-host shows.
        return ['detected_name'=>$name, 'candidates'=>array_values($candidates),
            'suggestion'=>count($candidates) === 1 ? array_values($candidates)[0] : null];
    }
}
