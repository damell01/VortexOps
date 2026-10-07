<?php

namespace App\Jobs;

use App\Models\Show;
use App\Services\ShowCoverImages;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class StoreShowCover implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 45;
    public int $uniqueFor = 3600;

    public function __construct(public int $showId, public string $source) {}

    public function uniqueId(): string { return $this->showId.':'.hash('sha256', $this->source); }
    public function backoff(): array { return [60, 300, 900]; }

    public function handle(ShowCoverImages $images): void
    {
        Cache::lock('show-cover:'.$this->showId, 50)->block(5, function () use ($images) {
            $show = Show::find($this->showId);
            if (! $show) return;
            // A queued old cover must not overwrite newer artwork from a later scrape.
            $latest = $show->raw_import_payload['cover_image_url'] ?? null;
            if ($latest && $latest !== $this->source) return;
            $disk = Storage::disk('public');
            $path = 'show-covers/'.$show->id.'-'.substr(hash('sha256', $this->source), 0, 24).'.webp';
            if (! $disk->exists($path)) {
                $this->validateSource();
                $response = Http::connectTimeout(5)->timeout(20)->withOptions([
                    'allow_redirects' => false,
                    'on_headers' => function ($response) {
                        if ((int) $response->getHeaderLine('Content-Length') > 8 * 1024 * 1024) throw new \RuntimeException('Show cover exceeds download limit.');
                    },
                    'progress' => function ($total, $downloaded) {
                        if ($downloaded > 8 * 1024 * 1024) throw new \RuntimeException('Show cover exceeds download limit.');
                    },
                ])->get($this->source);
                $response->throw();
                if ($response->status() !== 200 || strlen($response->body()) > 8 * 1024 * 1024) throw new \RuntimeException('Invalid show cover response.');
                $bytes = $images->encode($response->body());
                if (! $disk->put($path, $bytes)) throw new \RuntimeException('Could not store show cover.');
            }
            $old = $show->cover_image_url;
            $show->forceFill(['cover_image_url' => '/storage/'.$path])->saveQuietly();
            // Remove replaced local covers only after the new file and database write succeed.
            if (is_string($old) && preg_match('~^(?:https?://[^/]+)?/storage/(show-covers/'.$show->id.'-[a-f0-9]{24}\\.webp)$~', $old, $match) && $match[1] !== $path) {
                $disk->delete($match[1]);
            }
        });
    }

    private function validateSource(): void
    {
        $host = strtolower((string) parse_url($this->source, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($this->source, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || parse_url($this->source, PHP_URL_USER) || parse_url($this->source, PHP_URL_PORT)) {
            throw new \RuntimeException('Invalid show cover URL.');
        }
        // Restrict downloads to image CDNs used by Whatnot; never follow redirects.
        $allowed = false;
        foreach (['whatnot.com', 'cloudfront.net', 'imgix.net', 'googleusercontent.com', 'amazonaws.com'] as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) { $allowed = true; break; }
        }
        $addresses = gethostbynamel($host) ?: [];
        if (! $allowed || $addresses === []) throw new \RuntimeException('Unrecognized show cover host.');
        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) throw new \RuntimeException('Invalid show cover host address.');
        }
    }
}
