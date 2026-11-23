<?php

namespace Tests\Feature\Shows;

use App\Jobs\StoreShowCover;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Whatnot's image CDN resets connections now and then ("cURL error 35:
 * Connection reset by peer"). The cover is cosmetic, so a drop is retried
 * quietly instead of surfacing as a production error.
 */
class ShowCoverDownloadIsQuietOnNetworkDropsTest extends TestCase
{
    private string $source = 'https://images.whatnot.com/livestream_thumbnails/test.jpeg';

    private function download(StoreShowCover $job): mixed
    {
        return (new \ReflectionMethod(StoreShowCover::class, 'download'))->invoke($job);
    }

    public function test_a_dropped_connection_does_not_throw(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 35: Recv failure: Connection reset by peer'));

        $this->assertNull($this->download(new StoreShowCover(1, $this->source)));
    }

    public function test_a_server_error_is_quiet_but_a_missing_image_still_fails(): void
    {
        Http::fake(['*' => Http::sequence()->push('', 503)->push('', 404)]);
        $job = new StoreShowCover(1, $this->source);

        $this->assertNull($this->download($job), '503 is temporary');

        $this->expectException(RequestException::class);
        $this->download($job);
    }
}
