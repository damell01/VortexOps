<?php

namespace Tests\Feature\Shows;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * The scraper's date reader used to accept exactly one shape.
 *
 * It did String(value) and checked for a "T" — correct for an ISO string and
 * wrong for every other way a JSON API hands back a time. Epoch seconds, epoch
 * milliseconds and { iso: … } wrappers all stringify into something with no
 * "T" in it, so they fell through to a text parser that does not read numbers,
 * and the show arrived with a title and a null date. Rows in that state are
 * discarded on the PHP side, which is how a run that had genuinely fetched
 * shows reported creating none of them.
 *
 * These run the real function out of the real file, so a rewrite that quietly
 * drops a format is caught here rather than on the next live import.
 */
class WhatnotTimestampCoercionTest extends TestCase
{
    private const SCRIPT = 'scripts/whatnot-scraper.cjs';

    /**
     * Lift coerceDateish out of the scraper and run it over the given inputs.
     *
     * @param  array<int, mixed>  $inputs
     * @return array<int, string>
     */
    private function coerce(array $inputs): array
    {
        $source = file_get_contents(base_path(self::SCRIPT));

        $start = strpos($source, 'function coerceDateish(');
        $this->assertNotFalse($start, 'coerceDateish is gone from ' . self::SCRIPT);

        // Walk the braces to the end of the declaration rather than guessing a
        // line count — the function moves as the file changes.
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
        $this->assertNotNull($end, 'Could not find the end of coerceDateish');

        $harness = substr($source, $start, $end - $start + 1)
            . "\nconst inputs = JSON.parse(process.argv[2]);\n"
            . "process.stdout.write(JSON.stringify(inputs.map(coerceDateish)));\n";

        $file = tempnam(sys_get_temp_dir(), 'coerce') . '.cjs';
        file_put_contents($file, $harness);

        try {
            $process = new Process(['node', $file, json_encode($inputs)]);
            $process->run();

            $this->assertTrue(
                $process->isSuccessful(),
                'node failed: ' . $process->getErrorOutput(),
            );

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

    public function test_an_iso_string_passes_through_untouched(): void
    {
        $this->assertSame(
            ['2026-08-21T23:00:00Z'],
            $this->coerce(['2026-08-21T23:00:00Z']),
        );
    }

    public function test_epoch_seconds_become_an_iso_string(): void
    {
        // Whatnot's startTime is the field the live run carried, and a number
        // here is what the old String()-and-look-for-T check could not read.
        $this->assertStringStartsWith('2026-08-22T', $this->coerce([1787356800])[0]);
    }

    public function test_epoch_milliseconds_are_not_read_as_seconds(): void
    {
        // The distinction that matters: read as seconds, 1787356800000 lands in
        // the year 58600 — a date that parses cleanly and is entirely wrong.
        $this->assertStringStartsWith('2026-08-22T', $this->coerce([1787356800000])[0]);
    }

    public function test_a_wrapper_object_is_unwrapped(): void
    {
        $this->assertStringStartsWith('2026-08-21', $this->coerce([['iso' => '2026-08-21T23:00:00Z']])[0]);
    }

    public function test_a_space_separated_timestamp_is_read_as_iso(): void
    {
        $this->assertSame(['2026-08-21T23:00:00'], $this->coerce(['2026-08-21 23:00:00']));
    }

    public function test_nothing_at_all_stays_empty(): void
    {
        // The importer distinguishes "no date" from "unreadable date", and it
        // can only do that if an absent value does not become a string.
        $this->assertSame(['', '', ''], $this->coerce([null, '', []]));
    }

    public function test_an_unreadable_string_is_returned_as_written(): void
    {
        // So the PHP side can log what it actually got instead of a blank.
        $this->assertSame(['sometime last Tuesday'], $this->coerce(['sometime last Tuesday']));
    }
}
