<?php

namespace Tests\Feature\Shows;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * The other half of the duration chain: reading it off Whatnot's page.
 *
 * The PHP side is covered by ShowDurationSurvivesImportTest — a duration that
 * arrives is stored, zero included. This covers what arrives: the scraper
 * takes the text out of the analytics page's "Show Duration" tile and turns it
 * into minutes, and if that returns null for a format Whatnot actually uses,
 * the show reads 0h 0m for a reason nothing downstream can see.
 *
 * Run against the real function in the real file, so a rewrite that drops a
 * format fails here rather than on the next import.
 */
class WhatnotDurationParsingTest extends TestCase
{
    private const SCRIPT = 'scripts/whatnot-scraper.cjs';

    /**
     * @param  array<int, string>  $inputs
     * @return array<int, int|null>
     */
    private function parse(array $inputs): array
    {
        $source = file_get_contents(base_path(self::SCRIPT));

        $start = strpos($source, 'function parseDurationToMinutes(');
        $this->assertNotFalse($start, 'parseDurationToMinutes is gone from ' . self::SCRIPT);

        $depth = 0;
        $end   = null;
        for ($i = strpos($source, '{', $start); $i < strlen($source); $i++) {
            if ($source[$i] === '{') {
                $depth++;
            } elseif ($source[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }
        $this->assertNotNull($end);

        $harness = substr($source, $start, $end - $start + 1)
            . "\nconst inputs = JSON.parse(process.argv[2]);\n"
            . "process.stdout.write(JSON.stringify(inputs.map(parseDurationToMinutes)));\n";

        $file = tempnam(sys_get_temp_dir(), 'dur') . '.cjs';
        file_put_contents($file, $harness);

        try {
            $process = new Process(['node', $file, json_encode($inputs)]);
            $process->run();

            $this->assertTrue($process->isSuccessful(), 'node failed: ' . $process->getErrorOutput());

            return json_decode($process->getOutput(), true);
        } finally {
            @unlink($file);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $probe = new Process(['node', '--version']);
        $probe->run();

        if (! $probe->isSuccessful()) {
            $this->markTestSkipped('node is not available');
        }
    }

    public function test_hours_and_minutes(): void
    {
        $this->assertSame([154, 60, 480], $this->parse(['2h 34m', '1h 0m', '8h 0m']));
    }

    public function test_the_zero_whatnot_actually_shows(): void
    {
        // The value on the analytics page of the show that prompted all this.
        // It must come back as 0 and not as null: zero is an answer, null is
        // the absence of one, and they mean different things to a payout.
        $this->assertSame([0], $this->parse(['0h 0m']));
    }

    public function test_minutes_alone(): void
    {
        $this->assertSame([53, 90], $this->parse(['53m', '90 min']));
    }

    public function test_a_clock_style_duration(): void
    {
        $this->assertSame([90], $this->parse(['1:30:00']));
    }

    public function test_nothing_at_all_is_null_not_zero(): void
    {
        // An empty tile is Whatnot not saying, which must not be recorded as a
        // show that lasted no time.
        $this->assertSame([null, null], $this->parse(['', '—']));
    }
}
