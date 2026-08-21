<?php

namespace Tests\Feature\Console;

use App\Services\WhatnotScraper;
use Mockery;
use Tests\TestCase;

/**
 * The Seller Hub's pages are refused and its API is not, so the way in is to
 * call the API — which means knowing what to call.
 *
 * This command answers that from two sources, and they fail differently. The
 * bundles list every operation the app knows about, including ones only reached
 * from pages that cannot be opened. The live network shows which are actually
 * in use, with this session, right now. Reporting only one of them would either
 * hide the operation worth calling or claim one works when nothing has tried it.
 */
class WhatnotDiscoverApiTest extends TestCase
{
    private function finding(
        array $operations = [],
        array $liveCalls = [],
        ?array $introspection = null,
        int $scriptCount = 12,
        ?string $needle = null,
        array $needleHits = [],
        int $chunksScanned = 40,
        ?string $buildId = 'abc123',
    ): \Illuminate\Testing\PendingCommand {
        $scraper = Mockery::mock(WhatnotScraper::class);
        $scraper->shouldReceive('discoverApi')->andReturn([
            'operations'    => $operations,
            'liveCalls'     => $liveCalls,
            'introspection' => $introspection,
            'scriptCount'   => $scriptCount,
            'needle'        => $needle,
            'needleHits'    => $needleHits,
            'chunksScanned' => $chunksScanned,
            'buildId'       => $buildId,
        ]);

        $this->app->instance(WhatnotScraper::class, $scraper);

        return $this->artisan('whatnot:discover-api');
    }

    private function op(string $name, string $kind = 'query'): array
    {
        return ['name' => $name, 'kind' => $kind, 'from' => 'main.js'];
    }

    public function test_it_surfaces_the_operations_worth_calling(): void
    {
        $this->finding([
            $this->op('SellerShowsList'),
            $this->op('CurrentUserFollowedLivestreamTags'),
            $this->op('UpdateAvatarColour', 'mutation'),
        ])
            ->expectsOutputToContain('SellerShowsList')
            ->assertSuccessful();
    }

    public function test_it_filters_out_the_noise(): void
    {
        // A large bundle mentions hundreds of operations and almost none of
        // them are about shows. Listing all of them is the same as listing
        // none.
        $this->finding([
            $this->op('SellerShowsList'),
            $this->op('UpdateAvatarColour', 'mutation'),
        ])->doesntExpectOutputToContain('UpdateAvatarColour');
    }

    public function test_the_calls_the_page_actually_made_are_reported_first(): void
    {
        // Worth more than anything scraped out of a bundle: these are known to
        // work, with this session, at this moment.
        $this->finding(
            [$this->op('SellerShowsList')],
            [['method' => 'GET', 'url' => 'https://www.whatnot.com/services/graphql/?operationName=SellerShowsList']],
        )
            ->expectsOutputToContain('Calls this page made')
            ->expectsOutputToContain('/services/graphql/');
    }

    public function test_finding_nothing_is_reported_as_a_failure(): void
    {
        // An empty result means the page did not finish or the bundles were
        // unreachable — not that the Seller Hub uses no API.
        $this->finding()
            ->expectsOutputToContain('Nothing found')
            ->assertFailed();
    }

    public function test_matching_no_filter_says_so_rather_than_looking_empty(): void
    {
        // "0 worth a look" with nothing under it reads as a broken command.
        $this->finding([$this->op('UpdateAvatarColour', 'mutation')])
            ->expectsOutputToContain('None matched the filter')
            ->assertSuccessful();
    }

    // ── Introspection: the schema answering for itself ────────────────────

    public function test_an_enabled_schema_is_reported_as_the_authoritative_list(): void
    {
        // Names scraped from minified JavaScript are guesswork about what a
        // bundler left behind. A schema that answers is the API stating what it
        // accepts, so it is worth saying which one we are looking at.
        $this->finding(
            [$this->op('SellerShowsList', 'schema')],
            [],
            ['status' => 200, 'fields' => ['sellerShows', 'me', 'livestreams'], 'error' => null],
        )
            ->expectsOutputToContain('Introspection is enabled')
            ->expectsOutputToContain('SellerShowsList');
    }

    public function test_a_disabled_schema_says_so_rather_than_looking_empty(): void
    {
        // Introspection off is the normal production setting. Printing nothing
        // would read as the command failing, and send somebody debugging a
        // command that worked perfectly.
        $this->finding(
            [],
            [['method' => 'POST', 'url' => 'https://www.whatnot.com/services/graphql/?operationName=Whatever']],
            ['status' => 400, 'fields' => null, 'error' => 'GraphQL introspection is not allowed'],
        )
            ->expectsOutputToContain('introspection is not allowed')
            ->assertSuccessful();
    }

    public function test_no_scripts_at_all_is_distinguished_from_scripts_with_nothing_in_them(): void
    {
        // Two different failures that produce the same empty list: the page
        // referenced no bundles, or it referenced bundles holding no operation
        // names. Only the first means the discovery itself did not run.
        $this->finding([], [['method' => 'GET', 'url' => '/x']], null, 0)
            ->expectsOutputToContain('no bundles to read');
    }

    // ── Are we even reading the right files? ──────────────────────────────

    public function test_a_needle_that_is_found_shows_what_surrounds_it(): void
    {
        // The context is the point, not the hit: it is the syntax the patterns
        // have to match, and reading it is faster than guessing at what a
        // bundler emitted.
        $this->finding(
            [], [], null, 12,
            'CurrentUserFollowedLivestreamTags',
            [['from' => 'main-abc.js', 'context' => 'kind:"Name",value:"CurrentUserFollowedLivestreamTags"']],
        )
            ->expectsOutputToContain('found in 1 bundle')
            ->expectsOutputToContain('kind:"Name"');
    }

    public function test_a_needle_that_is_missing_says_we_are_looking_in_the_wrong_place(): void
    {
        // The distinction three rounds were lost to. An empty result means
        // either the operations are not there or these are not the files —
        // and a name known to exist tells the two apart.
        $this->finding([], [], null, 12, 'CurrentUserFollowedLivestreamTags', [])
            ->expectsOutputToContain('is in none of the bundles')
            ->expectsOutputToContain('looking in the wrong place');
    }

    public function test_no_needle_means_no_extra_output(): void
    {
        $this->finding([$this->op('SellerShowsList')])
            ->doesntExpectOutputToContain('is in none of the bundles');
    }

    // ── How much was actually read ────────────────────────────────────────

    public function test_it_says_how_many_chunks_it_got_through(): void
    {
        // "69 operations" means nothing on its own: the same number can come
        // from one page's chunks or from four hundred, and only the second is
        // evidence that the show queries are genuinely not there.
        $this->finding([$this->op('SellerShowsList')], [], null, 12, null, [], 317)
            ->expectsOutputToContain('Read 317 chunk(s)');
    }

    public function test_a_missing_build_id_is_called_out(): void
    {
        // App Router ships no __NEXT_DATA__ and no _buildManifest.js, so the
        // manifest lookup comes back empty on a perfectly normal Next app.
        // Silently falling back would leave the reader thinking every route's
        // code had been read when only one page's had.
        $this->finding([$this->op('SellerShowsList')], [], null, 12, null, [], 40, null)
            ->expectsOutputToContain('no buildId');
    }
}
