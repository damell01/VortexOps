<?php

namespace Tests\Feature\Console;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * with-xvfb.sh picks a free X display by checking for /tmp/.X{n}-lock files
 * across :99-:199. Xvfb removes its own lock on a clean exit, but a kill -9,
 * a force-cleared browser lock, or a container restart leaves the lock file
 * behind forever — the display looks permanently taken even though nothing
 * is using it. Enough of those and every one of the 101 slots looks occupied,
 * which is exactly what took down every channel's analytics backfill at once.
 *
 * These run the real script end to end (it needs Xvfb/setsid actually
 * installed) against display numbers at the top of the range, first
 * confirmed free, so a real display already in use elsewhere is never
 * touched.
 */
class WithXvfbStaleLockTest extends TestCase
{
    private string $scriptPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scriptPath = base_path('scripts/with-xvfb.sh');
    }

    /** A PID that is guaranteed not to belong to any running process. */
    private function deadPid(): int
    {
        $process = new Process(['true']);
        $process->start();
        $pid = $process->getPid();
        $process->wait();

        return $pid ?? 999999;
    }

    private function freeDisplayNumberForTest(): int
    {
        for ($n = 199; $n >= 190; $n--) {
            if (! file_exists("/tmp/.X{$n}-lock")) {
                return $n;
            }
        }

        $this->markTestSkipped('No free display slot at the top of the range to test against.');
    }

    public function test_a_lock_file_owned_by_a_dead_process_is_reclaimed(): void
    {
        $n = $this->freeDisplayNumberForTest();
        $lock = "/tmp/.X{$n}-lock";
        file_put_contents($lock, str_pad((string) $this->deadPid(), 11, ' ', STR_PAD_LEFT)."\n");

        try {
            $process = new Process([$this->scriptPath, 'true']);
            $process->setTimeout(20);
            $process->run();

            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $this->assertStringNotContainsString('no free X display', $process->getErrorOutput());
        } finally {
            @unlink($lock);
            @unlink("/tmp/.X11-unix/X{$n}");
        }
    }

    public function test_a_lock_file_owned_by_a_live_process_is_left_alone(): void
    {
        $n = $this->freeDisplayNumberForTest();
        $lock = "/tmp/.X{$n}-lock";

        $holder = new Process(['sleep', '15']);
        $holder->start();
        usleep(200_000);

        file_put_contents($lock, str_pad((string) $holder->getPid(), 11, ' ', STR_PAD_LEFT)."\n");

        try {
            $process = new Process([$this->scriptPath, 'sh', '-c', 'echo "DISPLAY=$DISPLAY"']);
            $process->setTimeout(20);
            $process->run();

            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $this->assertStringNotContainsString("DISPLAY=:{$n}\n", $process->getOutput(), 'the live-locked display must not be handed out');
            $this->assertTrue(file_exists($lock), 'a lock still held by a live process must not be removed');
        } finally {
            $holder->stop(1);
            @unlink($lock);
        }
    }
}
