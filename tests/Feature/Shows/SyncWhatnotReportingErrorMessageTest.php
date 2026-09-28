<?php

namespace Tests\Feature\Shows;

use App\Console\Commands\SyncWhatnotReporting;
use Tests\TestCase;

/**
 * A failure with no message text used to render as "…failed: " with nothing
 * after the colon — the exact thing that made a real admin unable to tell
 * why an Analytics Backfill run failed from the UI's run-output panel. Every
 * failure now carries at least a class name and a file:line to look up.
 */
class SyncWhatnotReportingErrorMessageTest extends TestCase
{
    private function describeError(\Throwable $e): string
    {
        $command = new SyncWhatnotReporting();
        $method = new \ReflectionMethod($command, 'describeError');
        $method->setAccessible(true);

        return $method->invoke($command, $e);
    }

    public function test_a_normal_exception_message_passes_through_unchanged(): void
    {
        $this->assertSame(
            'session expired, run whatnot:login',
            $this->describeError(new \RuntimeException('session expired, run whatnot:login'))
        );
    }

    public function test_an_exception_with_no_message_still_says_something_useful(): void
    {
        $description = $this->describeError(new \RuntimeException(''));

        $this->assertStringContainsString('RuntimeException', $description);
        $this->assertStringContainsString('with no message at', $description);
    }

    public function test_a_message_of_only_whitespace_is_treated_as_empty(): void
    {
        $description = $this->describeError(new \RuntimeException("   \n  "));

        $this->assertStringContainsString('with no message at', $description);
    }
}
