<?php

namespace Database\Seeders;

use App\Models\Streamer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * One-time roster import — creates a login (@vortexops.tech, temp password)
 * plus a linked Streamer profile for each new person, so they can sign in
 * and immediately see their own shows/payouts. Payout type/rate are left
 * blank; set those on each Streamer before running a real payout.
 *
 * Idempotent: safe to re-run (updateOrCreate on email / streamer name), but
 * a re-run does NOT reset the temp password on accounts that already exist —
 * only genuinely new accounts get the freshly generated password below.
 *
 * Deliberately skips Josh and Daniel — they already have accounts.
 */
class StreamerOnboardingSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);

        // Generated fresh every run, never hardcoded/committed — printed once
        // at the end for you to hand out. Existing accounts keep their password.
        $tempPassword = Str::password(14);

        $roster = [
            // ── General roster ──────────────────────────────────────────────
            ['nickname' => 'Jason',     'legal_name' => 'Jason Santasiero', 'notes' => null],
            ['nickname' => 'Brandon',   'legal_name' => 'Brandon Ziegler',  'notes' => null],
            ['nickname' => 'Trey',      'legal_name' => 'Trey Chamberlain', 'notes' => null],
            ['nickname' => 'Ty',        'legal_name' => 'Tyler Ramirez',    'notes' => 'Also streams as "Ty & Luna" — same person/account.'],
            ['nickname' => 'Trainer J', 'legal_name' => 'Justin Bishop',    'notes' => null],
            ['nickname' => 'Poketazz',  'legal_name' => null,               'notes' => null],
            ['nickname' => 'Diamo',     'legal_name' => null,               'notes' => null],
            ['nickname' => 'Tyler',     'legal_name' => 'Tyler McMillen',   'notes' => null],

            // ── Butler pop-up group (separate accounts per your call) ───────
            ['nickname' => 'Butler',    'legal_name' => null, 'notes' => 'Pop-up streamer, any channel — mainly VortexShop. Does not record by stream.'],
            ['nickname' => 'Chickadee', 'legal_name' => null, 'notes' => 'Pop-up streamer, any channel — mainly VortexShop. Does not record by stream.'],
            ['nickname' => 'Dom',       'legal_name' => null, 'notes' => 'Pop-up streamer, any channel — mainly VortexShop. Does not record by stream.'],

            // ── Vortex Collects (VCX) roster ─────────────────────────────────
            ['nickname' => 'Natbat',    'legal_name' => null, 'notes' => 'Vortex Collects (VCX) roster.'],
            ['nickname' => 'Lil Twang', 'legal_name' => 'Bethany', 'notes' => 'Vortex Collects (VCX) roster.'],
            ['nickname' => 'Jay',       'legal_name' => null, 'notes' => 'Also goes by "Jay Walker". Vortex Collects (VCX) roster.'],
            ['nickname' => 'BMoney',    'legal_name' => null, 'notes' => 'Also goes by "B$". Vortex Collects (VCX) roster.'],
            ['nickname' => 'Cayden',    'legal_name' => null, 'notes' => 'Vortex Collects (VCX) roster.'],
            ['nickname' => 'Cate',      'legal_name' => null, 'notes' => 'Vortex Collects (VCX) roster.'],
            ['nickname' => 'Dennis',    'legal_name' => null, 'notes' => 'Vortex Collects (VCX) roster.'],
        ];

        $created = [];

        foreach ($roster as $entry) {
            $emailLocalPart = Str::slug($entry['nickname'], '');
            $email          = "{$emailLocalPart}@vortexops.tech";

            $existingUser = User::where('email', $email)->first();
            $isNewUser    = $existingUser === null;

            // Re-running this seeder must never silently reset an existing
            // teammate's password to this run's fresh temp value — carry the
            // existing hash forward as a no-op instead of touching it.
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name'              => $entry['legal_name'] ?: $entry['nickname'],
                    'password'          => $isNewUser ? Hash::make($tempPassword) : $existingUser->password,
                    'email_verified_at' => $existingUser?->email_verified_at ?? now(),
                ]
            );

            if (! $user->hasRole('streamer')) {
                $user->assignRole('streamer');
            }

            Streamer::updateOrCreate(
                ['name' => $entry['nickname']],
                [
                    'legal_name' => $entry['legal_name'],
                    'user_id'    => $user->id,
                    'status'     => 'active',
                    'notes'      => $entry['notes'],
                ]
            );

            if ($isNewUser) {
                $created[] = ['nickname' => $entry['nickname'], 'email' => $email];
            }
        }

        $this->command?->newLine();
        if (empty($created)) {
            $this->command?->info('No new accounts created — everyone in the roster already exists.');
            return;
        }

        $this->command?->info('Created ' . count($created) . ' new streamer account(s). Temp password for all of them: ' . $tempPassword);
        $this->command?->table(['Nickname', 'Email'], array_map(fn ($c) => [$c['nickname'], $c['email']], $created));
        $this->command?->warn('Have each person change their password after first login.');
    }
}
