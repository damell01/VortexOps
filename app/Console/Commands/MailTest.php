<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Prove the mail configuration end to end, before a real notification does.
 *
 * There was no way to find out whether mail worked short of triggering a
 * workflow that sends one and watching for it — and when nothing arrived,
 * nothing said whether the driver was wrong, the key was missing, the from
 * address was unverified, or the mail simply went to the log file. Each of
 * those looks the same from the outside.
 */
class MailTest extends Command
{
    protected $signature = 'mail:test
                            {email? : Where to send it. Defaults to the owner address.}';

    protected $description = 'Send a test email through the configured mailer and report exactly what happened';

    public function handle(): int
    {
        $mailer = config('mail.default');
        $to     = $this->argument('email')
            ?: config('app.owner_email')
            ?: config('mail.from.address');

        $this->line('');
        $this->line('  <fg=gray>Mailer</>      ' . $mailer);
        $this->line('  <fg=gray>From</>        ' . config('mail.from.address') . ' (' . config('mail.from.name') . ')');
        $this->line('  <fg=gray>To</>          ' . $to);

        if ($mailer === 'resend') {
            $key = config('services.resend.key');

            $this->line('  <fg=gray>Resend key</>  ' . ($key
                ? substr($key, 0, 6) . str_repeat('•', 8) . ' (' . strlen($key) . ' chars)'
                : '<fg=red>MISSING</>'));

            if (! $key) {
                $this->newLine();
                $this->error('RESEND_API_KEY is empty, so every send will fail.');
                $this->line('Set it in .env, then run: php artisan config:clear');

                return self::FAILURE;
            }
        }

        $this->newLine();

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn("MAIL_MAILER is \"{$mailer}\", so nothing leaves the server.");
            $this->line($mailer === 'log'
                ? 'The message below went to storage/logs/laravel.log.'
                : 'The message below was kept in memory and discarded.');
            $this->line('Set MAIL_MAILER=resend in .env for real delivery.');
            $this->newLine();
        }

        if (! $to) {
            $this->error('No recipient. Pass one as an argument or set MAIL_FROM_ADDRESS.');

            return self::FAILURE;
        }

        try {
            Mail::raw(
                "This is a test from VortexOps.\n\n"
                . 'Sent ' . now()->toDayDateTimeString() . " via the \"{$mailer}\" mailer.\n"
                . "If you are reading this in an inbox, mail delivery is working.",
                fn ($message) => $message->to($to)->subject('VortexOps mail test'),
            );
        } catch (\Throwable $e) {
            $this->error('Send failed: ' . $e->getMessage());
            $this->newLine();

            // The three that account for almost every failure, named rather
            // than left for someone to infer from a stack trace.
            $this->line('<fg=gray>Common causes:</>');
            $this->line('<fg=gray>  · RESEND_API_KEY wrong or revoked</>');
            $this->line('<fg=gray>  · MAIL_FROM_ADDRESS is on a domain not verified in Resend</>');
            $this->line('<fg=gray>  · config cached before .env was updated — run php artisan config:clear</>');

            return self::FAILURE;
        }

        $this->info('Accepted by the "' . $mailer . '" mailer without error.');

        if ($mailer === 'resend') {
            $this->line('Check the inbox, and Resend\'s dashboard if it does not arrive —');
            $this->line('a message can be accepted here and still be bounced or blocked there.');
        }

        return self::SUCCESS;
    }
}
