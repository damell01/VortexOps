<?php

namespace App\Services;

use App\Exceptions\WhatnotBlockedException;
use App\Support\WhatnotBrowserLock;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Safety wrapper for analytics imports.
 *
 * The legacy Node scraper is intentionally tolerant of a role-switch timeout and
 * can continue on whichever seller channel is currently active. That is unsafe
 * for a multi-channel import: valid-looking data can be attributed to the wrong
 * WhatnotChannel. It can also parse a Cloudflare interstitial as a one-row show
 * named "www.whatnot.com" when the challenge arrives after navigation.
 *
 * Keep the existing scraper implementation for every other mode, but make the
 * analytics entry point fail closed until the Node side positively completed the
 * requested role switch and returned real analytics rows.
 */
class SafeWhatnotScraper extends WhatnotScraper
{
    public function fetchShows(
        int $limit = 50,
        bool $debug = false,
        ?string $channelUsername = null,
        ?callable $onProgress = null,
        ?string $seedLiveId = null,
    ): array {
        $env = [
            'WHATNOT_DEBUG' => $debug ? '1' : '0',
            'WHATNOT_MODE' => 'analytics',
            'WHATNOT_LIMIT' => (string) $limit,
        ];

        if ($email = config('vortex.whatnot.email')) {
            $env['WHATNOT_EMAIL'] = $email;
        }

        if ($password = config('vortex.whatnot.password')) {
            $env['WHATNOT_PASSWORD'] = $password;
        }

        if ($channelUsername) {
            $env['WHATNOT_CHANNEL_NAME'] = $channelUsername;
        }

        if ($seedLiveId) {
            $env['WHATNOT_START_UUID'] = $seedLiveId;
        }

        $timeoutSeconds = max(1200, (int) ceil($limit / 50) * 1200);
        $process = new Process(
            [config('vortex.whatnot.node_bin', 'node'), base_path('scripts/whatnot-scraper.cjs')],
            base_path(),
            $env,
            null,
            $timeoutSeconds,
        );

        $stderrBuffer = '';
        $progressBuffer = '';
        $lock = WhatnotBrowserLock::make($timeoutSeconds + 600);

        $lock->block($timeoutSeconds, function () use ($process, $onProgress, &$stderrBuffer, &$progressBuffer): void {
            $process->run(function (string $type, string $buffer) use ($onProgress, &$stderrBuffer, &$progressBuffer): void {
                if ($type !== Process::ERR) {
                    return;
                }

                $stderrBuffer .= $buffer;

                if (! $onProgress) {
                    return;
                }

                $progressBuffer .= $buffer;
                $lines = preg_split('/\r?\n/', $progressBuffer);
                $progressBuffer = array_pop($lines) ?? '';

                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line !== '') {
                        $onProgress($line);
                    }
                }
            });

            if ($onProgress && trim($progressBuffer) !== '') {
                $onProgress(trim($progressBuffer));
                $progressBuffer = '';
            }
        });

        $stderr = trim($stderrBuffer !== '' ? $stderrBuffer : $process->getErrorOutput());
        $stdout = trim($process->getOutput());

        if ($stderr !== '') {
            Log::channel('stack')->warning('SafeWhatnotScraper stderr', [
                'output' => $stderr,
                'channel' => $channelUsername,
            ]);

            if ($debug && ! $onProgress) {
                fwrite(STDERR, $stderr . PHP_EOL);
            }
        }

        $this->throwIfBlocked($stderr);
        $this->throwIfChannelSwitchWasNotVerified($stderr, $channelUsername);

        if (! $process->isSuccessful()) {
            $message = $stderr ?: "Scraper exited with code {$process->getExitCode()}";
            throw new \RuntimeException("Whatnot scraper failed: {$message}");
        }

        if ($stdout === '') {
            return [];
        }

        $data = json_decode($stdout, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Whatnot scraper returned invalid JSON: ' . json_last_error_msg());
        }

        $rows = is_array($data) ? $data : [];
        $this->throwIfChallengeWasParsedAsShow($rows, $stderr);

        return $rows;
    }

    private function throwIfBlocked(string $stderr): void
    {
        if ($stderr === '') {
            return;
        }

        if (preg_match('/BOT_CHALLENGE|Security by Cloudflare|challenge did not clear|Cloudflare served a bot-protection challenge/i', $stderr)) {
            throw new WhatnotBlockedException(
                "Cloudflare blocked the Whatnot analytics page. No show data was imported.\n" .
                'The saved Whatnot login is still usable when /dashboard/home loads, but this browser/session must pass the Cloudflare challenge before analytics imports can resume.'
            );
        }
    }

    private function throwIfChannelSwitchWasNotVerified(string $stderr, ?string $channelUsername): void
    {
        if (! $channelUsername) {
            return;
        }

        $target = preg_quote($channelUsername, '/');

        $alreadyOnTarget = preg_match('/switchToChannel: already on target channel @[^\s]+ — no switch needed/i', $stderr) === 1;
        $switchCompleted = preg_match('/switchToChannel: done, URL now:/i', $stderr) === 1
            && preg_match('/switchToChannel: found channel option matching\s+["\x{201C}\x{201D}]?' . $target . '/iu', $stderr) === 1;

        $switchFailed = preg_match('/switchToChannel: gave up after .*continuing on the currently active channel/i', $stderr) === 1
            || preg_match('/switchToChannel: WARNING — channel .* not found/i', $stderr) === 1
            || preg_match('/Switch Role not found/i', $stderr) === 1;

        if ($switchFailed || (! $alreadyOnTarget && ! $switchCompleted)) {
            throw new \RuntimeException(
                "CHANNEL_SWITCH_FAILED: refused to import @{$channelUsername} because the scraper did not verify that channel became active. " .
                'No shows were imported for this channel.'
            );
        }
    }

    /** @param array<int,mixed> $rows */
    private function throwIfChallengeWasParsedAsShow(array $rows, string $stderr): void
    {
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $title = strtolower(trim((string) ($row['title'] ?? '')));
            $date = $row['show_date'] ?? null;

            if ($title === 'www.whatnot.com' && empty($date)) {
                throw new WhatnotBlockedException(
                    'Cloudflare/interstitial HTML was returned instead of Whatnot analytics. Refusing the fake "www.whatnot.com" show; no show data was imported.'
                );
            }
        }

        if ($rows !== [] && collect($rows)->every(fn ($row) => is_array($row) && empty($row['show_date'] ?? null))) {
            throw new \RuntimeException(
                'Whatnot analytics returned rows with no show dates. Refusing to import them because the page did not match valid show analytics.'
            );
        }
    }
}
