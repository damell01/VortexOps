<?php

namespace App\Console\Commands;

use App\Models\FulfillmentRun;
use App\Models\FulfillmentShift;
use App\Models\FulfillmentStation;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\StreamerLogItem;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Models\WhatnotShowOrder;
use App\Services\FulfillmentFlagService;
use App\Services\FulfillmentQueue;
use App\Services\FulfillmentRunService;
use App\Support\AdminModules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * A believable Fulfillment Center for the Dev site: shows mid-packing, one with
 * an issue, one paused, ready shows, a finished show waiting for approval, an
 * approved one, a week of shifts and shipments in every tracking state.
 *
 * Everything is made through the real services, so letters, timers, the event
 * log and the whiteboard ticker look exactly as they will for real shows.
 * Never runs in production. Every demo show is titled "DEMO · …" and listed in
 * the fulfillment_demo setting so --remove takes exactly what it made.
 */
class FulfillmentDemo extends Command
{
    protected $signature = 'fulfillment:demo
        {--refresh : Rebuild only when the demo is missing or older than 3 days (used by the Dev deploy)}
        {--remove : Delete the demo data and stop}
        {--logins : Only print the demo sign-ins}
        {--reset-password : Give the demo sign-ins a new password}
        {--hide-password : Leave the password out of the output (deploy logs)}';

    protected $description = 'Create (or refresh) Fulfillment Center demo data on a non-production site';

    private const SETTING = 'fulfillment_demo';

    private const LOGINS = 'fulfillment_demo_logins';

    public const ADMIN_EMAIL = 'fulfillment-admin@vortexops.test';

    public const WORKER_EMAIL = 'fulfillment-worker@vortexops.test';

    /** @var array{0: User, 1: User}|array{} */
    private array $accounts = [];

    public function handle(FulfillmentRunService $runs, FulfillmentFlagService $flags): int
    {
        if (app()->environment('production')) {
            $this->error('Demo data is never created in production.');
            return self::FAILURE;
        }

        $this->accounts = [];
        $state = json_decode((string) Setting::get(self::SETTING, '{}'), true) ?: [];
        if ($this->option('logins')) {
            $this->logins();
            return self::SUCCESS;
        }
        if ($this->option('remove')) {
            $this->remove($state);
            $this->info('Fulfillment demo removed.');
            return self::SUCCESS;
        }
        $fresh = isset($state['created_at']) && now()->diffInDays($state['created_at'], true) < 3
            && Show::query()->whereIn('id', $state['shows'] ?? [])->count() === count($state['shows'] ?? [1]);
        if ($this->option('refresh') && $fresh) {
            $this->info('Fulfillment demo is up to date.');
            $this->logins();
            return self::SUCCESS;
        }

        $this->remove($state);
        \App\Services\Notifier::$muted = true;
        try {
            $state = DB::transaction(fn () => $this->build($runs, $flags));
        } finally {
            \App\Services\Notifier::$muted = false;
        }
        Setting::set(self::SETTING, json_encode($state));
        $this->info('Fulfillment demo ready: ' . count($state['shows']) . ' shows, ' . count($state['users']) . ' team members.');
        $this->logins();

        return self::SUCCESS;
    }

    private function build(FulfillmentRunService $runs, FulfillmentFlagService $flags): array
    {
        foreach (['fulfillment', 'fulfillment_admin'] as $role) Role::findOrCreate($role, 'web');
        $modules = json_decode((string) Setting::get('enabled_admin_modules', '[]'), true) ?: [];
        if (! in_array('fulfillment', $modules, true)) {
            Setting::set('enabled_admin_modules', json_encode(array_values(array_merge($modules, ['fulfillment']))));
            AdminModules::flushMemo();
        }

        // The team. Random passwords: these accounts exist to be shown, not signed into.
        $person = function (string $name, string $role) {
            $user = User::query()->firstOrCreate(
                ['email' => Str::slug($name) . '.demo@vortexops.test'],
                ['name' => $name . ' (demo)', 'password' => bcrypt(Str::random(40))],
            );
            $user->syncRoles([$role]);
            return $user;
        };
        $crystal = $person('Crystal Rice', 'fulfillment_admin');
        $dennis = $person('Dennis Packer', 'fulfillment');
        $monica = $person('Monica Helper', 'fulfillment');
        $gabe = $person('Gabe Shipper', 'fulfillment');
        // The two accounts you actually sign in with to walk the flow.
        [$admin, $worker] = $this->loginAccounts();

        $stations = FulfillmentStation::query()->where('active', true)->orderBy('sort')->orderBy('id')->get();
        for ($i = $stations->count() + 1; $i <= 5; $i++) {
            $stations->push(FulfillmentStation::query()->create(['name' => 'Station ' . $i, 'active' => true, 'sort' => $i]));
        }
        $stations->each(fn ($s, $i) => $s->camera_label ? null : $s->update(['camera_label' => 'Cam ' . ($i + 1)]));
        [$s1, $s2, $s3, $s4] = $stations->take(4)->values()->all();

        $channel = WhatnotChannel::query()->firstOrCreate(['name' => 'Demo Channel'], ['status' => 'active']);
        $host = Streamer::query()->firstOrCreate(['name' => 'Jason (demo)'], ['status' => 'active']);
        $names = ['alpha_cards', 'bigbuyer22', 'cardcollector99', 'chasethebobby', 'clean_slabs', 'collectorking', 'gmen_cards', 'hobbyhank',
            'ivy_pulls', 'jumbo_jets', 'kingofcommons', 'lucky_lisa', 'mintcondition', 'nerdnation', 'oddball_rips', 'pokepaul', 'quickflip_q',
            'rookieRick', 'slabqueen', 'topload_tony', 'ultra_ursula', 'vaultvince', 'wax_wanda', 'xfactor', 'yugi_yolanda', 'Zed_rips'];
        $order = 7452100;
        $made = [];
        $show = function (string $title, int $daysAgo, int $buyers, array $assign, bool $slow = false) use (&$order, &$made, $channel, $host, $names) {
            $show = Show::query()->create([
                'title' => 'DEMO · ' . $title, 'show_date' => today()->subDays($daysAgo)->toDateString(),
                'start_time' => now()->subDays($daysAgo)->setTime(19, 0), 'status' => 'reconciled', 'is_operational' => true,
                'whatnot_channel_id' => $channel->id, 'is_slow_pack' => $slow,
            ]);
            $show->streamers()->attach($host->id, ['is_primary' => true]);
            $log = StreamerLogEntry::query()->create(['show_id' => $show->id, 'streamer_id' => $host->id, 'status' => 'admin_approved',
                'approval_status' => 'approved', 'submitted_at' => now()->subDays($daysAgo)->setTime(23, 0), 'streamer_reviewed_at' => now()->subDays($daysAgo)->setTime(23, 30)]);
            StreamerLogItem::query()->create(['streamer_log_entry_id' => $log->id, 'item_name' => 'Mystery pack', 'quantity' => 1, 'packed_quantity' => 0,
                'unit_cost' => 1, 'disposition' => 'sold', 'fulfillment_status' => StreamerLogItem::FULFILLMENT_PENDING]);
            foreach (collect($names)->shuffle()->take($buyers) as $username) {
                foreach (range(1, random_int(1, 3)) as $n) {
                    $order++;
                    $row = WhatnotShowOrder::query()->create(['show_id' => $show->id, 'buyer_username' => $username, 'whatnot_order_id' => (string) $order,
                        'item_name' => 'Mystery pack', 'quantity' => 1, 'total_price' => 0, 'status' => 'completed', 'show_date' => $show->show_date,
                        'raw_data' => ['_source' => 'demo']]);
                    Shipment::query()->create(['show_id' => $show->id, 'whatnot_order_id' => $row->id, 'buyer_username' => $username,
                        'item_count' => random_int(1, 3), 'status' => 'label_created', 'carrier' => 'USPS', 'tracking_number' => '9300120' . $order]);
                }
            }
            $show->fulfillmentUsers()->sync(collect($assign)->pluck('id')->all());
            $made[] = $show->id;
            return $show;
        };

        $bigGiveys = $show('Big Giveys Top Hit Tuesday', 1, 18, [$dennis]);
        $hurricane = $show('Hurricane Clearout', 1, 12, [$monica]);
        $midnight = $show('Midnight Mystery Pulls', 2, 9, [$gabe]);
        $warehouse = $show('Massive Warehouse', 2, 10, [$worker]);
        $fullSend = $show('Saturday Full Send', 3, 22, [], true);
        $restock = $show('$100,000 Restock', 4, 8, [$dennis, $monica]);
        $boxGiveys = $show('Box Giveys All Stream', 6, 7, [$monica]);

        app(FulfillmentQueue::class)->sync();
        $pack = function (int $runId, int $count, User $who) use ($runs) {
            $run = FulfillmentRun::query()->findOrFail($runId);
            foreach ($run->buyers()->orderByRaw('LENGTH(letter)')->orderBy('letter')->take($count)->get() as $buyer) {
                $runs->markPacked($runId, $buyer->id, $who);
            }
            $next = $run->buyers()->where('status', 'waiting')->orderByRaw('LENGTH(letter)')->orderBy('letter')->first();
            if ($next) $runs->selectBuyer($runId, $next->id, $who);
        };
        $ago = fn (int $runId, int $minutes) => DB::table('fulfillment_runs')->where('id', $runId)->update(['started_at' => now()->subMinutes($minutes)]);

        // Packing now at Station 1.
        $r1 = $runs->start($bigGiveys, $s1->id, $crystal, [$dennis->id]);
        $pack($r1, 7, $dennis);
        $ago($r1, 74);

        // Packing at Station 2, with a damaged item flagged on the current buyer.
        $r2 = $runs->start($hurricane, $s2->id, $crystal, [$monica->id]);
        $pack($r2, 8, $monica);
        $current = FulfillmentRun::query()->find($r2)->current_buyer_id;
        $flags->report($monica, $hurricane->id, 'damaged', 'Top loader cracked on one slab', $r2, $current);
        $ago($r2, 51);

        // Paused at Station 4 for a break.
        $r4 = $runs->start($midnight, $s4->id, $crystal, [$gabe->id]);
        $pack($r4, 3, $gabe);
        $runs->pause($r4, $gabe, 'Lunch break');
        $ago($r4, 38);

        // Ready: one placed on Station 3, one long job still unassigned.
        $runs->assign($warehouse, [$worker->id], $admin, $s3->id);

        // Finished and acknowledged, waiting for the manager's approval.
        $r5 = $runs->start($restock, $s3->id, $crystal, [$dennis->id, $monica->id]);
        $pack($r5, 99, $dennis);
        $runs->finish($r5, $dennis);
        $runs->acknowledge($r5, $dennis);

        // Approved earlier in the week, with shipments out the door.
        $r6 = $runs->start($boxGiveys, $s3->id, $crystal, [$monica->id]);
        $pack($r6, 99, $monica);
        $runs->finish($r6, $monica);
        $runs->acknowledge($r6, $monica);
        $runs->setChecked($r6, $crystal, $runs->signOffProgress($r6)['kind'], null, true);
        $runs->signOff($r6, $crystal, 'Crystal Rice', 'Counts match the labels');
        DB::table('fulfillment_runs')->whereIn('id', [$r5, $r6])->update(['started_at' => now()->subDays(1)->setTime(14, 5), 'finished_at' => now()->subDays(1)->setTime(16, 40)]);
        Shipment::query()->where('show_id', $boxGiveys->id)->each(fn ($s, $i) => $s->update(['status' => $i % 3 === 0 ? 'in_transit' : 'delivered']));
        Shipment::query()->where('show_id', $restock->id)->each(fn ($s, $i) => $s->update(['status' => $i % 2 ? 'shipped' : 'in_transit']));

        // This week's schedule, day and night shifts, with today's shows on them.
        $week = now(\App\Support\BusinessTime::zone())->startOfWeek();
        $shifts = [];
        foreach (range(0, 4) as $d) {
            $day = $week->copy()->addDays($d);
            foreach ([[$dennis, 8, 16, $s1, 'day'], [$monica, 12, 20, $s2, 'day'], [$gabe, 19, 23, $s3, 'night']] as [$user, $from, $to, $station, $kind]) {
                $shift = FulfillmentShift::query()->create(['user_id' => $user->id, 'station_id' => $station->id, 'kind' => $kind,
                    'starts_at' => $day->copy()->setTime($from, 0)->utc(), 'ends_at' => $day->copy()->setTime($to, 0)->utc(), 'created_by' => $crystal->id]);
                $shifts[] = $shift->id;
            }
        }
        // The demo worker is on shift now at Station 3, with the show waiting for them.
        $local = now(\App\Support\BusinessTime::zone());
        $workerShift = FulfillmentShift::query()->create(['user_id' => $worker->id, 'station_id' => $s3->id, 'kind' => 'day',
            'starts_at' => $local->copy()->subHour()->startOfHour()->utc(), 'ends_at' => $local->copy()->addHours(6)->startOfHour()->utc(), 'created_by' => $admin->id]);
        $shifts[] = $workerShift->id;
        if (Schema::hasTable('fulfillment_shift_shows')) $workerShift->shows()->syncWithoutDetaching([$warehouse->id]);
        $todayDennis = FulfillmentShift::query()->where('user_id', $dennis->id)->whereDate('starts_at', now()->toDateString())->first();
        if ($todayDennis && Schema::hasTable('fulfillment_shift_shows')) $todayDennis->shows()->syncWithoutDetaching([$bigGiveys->id]);

        return [
            'created_at' => now()->toIso8601String(),
            'shows' => $made,
            'users' => [$crystal->id, $dennis->id, $monica->id, $gabe->id, $admin->id, $worker->id],
            'shifts' => $shifts,
        ];
    }

    /**
     * The demo admin and worker sign-ins. They keep their password between
     * refreshes (stored encrypted), so a rebuild never locks anyone out.
     *
     * @return array{0: User, 1: User}
     */
    private function loginAccounts(): array
    {
        if ($this->accounts) return $this->accounts;
        $stored = json_decode((string) Setting::get(self::LOGINS, '{}'), true) ?: [];
        $password = config('vortex.fulfillment_demo_password') ?: null;
        if (! $password && ! $this->option('reset-password') && isset($stored['password'])) {
            try {
                $password = Crypt::decryptString($stored['password']);
            } catch (\Throwable) {
                $password = null;
            }
        }
        $password = $password ?: Str::password(14, symbols: false);

        $accounts = [];
        foreach ([[self::ADMIN_EMAIL, 'Morgan Manager (demo)', 'fulfillment_admin'], [self::WORKER_EMAIL, 'Parker Packer (demo)', 'fulfillment']] as [$email, $name, $role]) {
            Role::findOrCreate($role, 'web');
            $user = User::query()->firstOrNew(['email' => $email]);
            $user->name = $name;
            if (! $user->exists || ! Hash::check($password, (string) $user->password)) $user->password = Hash::make($password);
            $user->save();
            $user->syncRoles([$role]);
            $accounts[] = $user;
        }
        Setting::set(self::LOGINS, json_encode(['password' => Crypt::encryptString($password)]));

        return $this->accounts = $accounts;
    }

    /** Prints the two sign-ins and the walkthrough. */
    private function logins(): void
    {
        [$admin, $worker] = $this->loginAccounts();
        $password = $this->option('hide-password') ? '(hidden — run: php artisan fulfillment:demo --logins)'
            : Crypt::decryptString(json_decode((string) Setting::get(self::LOGINS), true)['password']);
        $url = rtrim((string) config('app.url'), '/');

        $this->newLine();
        $this->line('  <options=bold>Fulfillment demo sign-ins</>  (' . $url . '/admin/login)');
        $this->line('    Manager:  ' . $admin->email . '   password: ' . $password);
        $this->line('    Packer:   ' . $worker->email . '   password: ' . $password);
        $this->newLine();
        $this->line('  <options=bold>Try the flow</>');
        $this->line('    1. Manager: Fulfillment Home shows Station 1 packing, Station 2 on hold with a damaged-item issue,');
        $this->line('       Station 4 paused, Station 3 ready for the packer and Station 5 free.');
        $this->line('    2. Manager: Assign show on Station 5 -> "Saturday Full Send" (queue page 2) with a packer.');
        $this->line('       Schedule & Activity -> Show logs -> "Review & sign off" on "$100,000 Restock": check off');
        $this->line('       the packages, type your name and Sign off show.');
        $this->line('    3. Packer (another browser or phone): Home -> Start on "Massive Warehouse", Pack buyers in order,');
        $this->line('       Flag an issue, Pause and resume, then Finish Show -> review -> Confirm, show is done.');
        $this->line('    4. Manager: watch it on the TV Whiteboard, resolve the issue (Open issues), sign the show off,');
        $this->line('       then check Reports for the packer numbers.');
        $this->line('    Start over any time: php artisan fulfillment:demo');
        $this->newLine();
    }

    /** Removes exactly what the last demo made: its shows (and everything hanging off them) and its shifts. */
    private function remove(array $state): void
    {
        $shows = array_values(array_filter(array_map('intval', $state['shows'] ?? [])));
        $shifts = array_values(array_filter(array_map('intval', $state['shifts'] ?? [])));
        // Anything titled as a demo show is demo data too (e.g. a half-finished earlier run).
        $shows = array_values(array_unique(array_merge($shows, Show::query()->where('title', 'like', 'DEMO · %')->pluck('id')->all())));
        if (! $shows && ! $shifts) return;

        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($shows, $shifts) {
                $runIds = DB::table('fulfillment_runs')->whereIn('show_id', $shows)->pluck('id')->all();
                $logIds = DB::table('streamer_log_entries')->whereIn('show_id', $shows)->pluck('id')->all();
                foreach (Schema::getTableListing() as $table) {
                    $table = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;
                    if ($runIds && Schema::hasColumn($table, 'run_id') && str_starts_with($table, 'fulfillment_')) DB::table($table)->whereIn('run_id', $runIds)->delete();
                    if ($logIds && Schema::hasColumn($table, 'streamer_log_entry_id')) DB::table($table)->whereIn('streamer_log_entry_id', $logIds)->delete();
                }
                foreach (Schema::getTableListing() as $table) {
                    $table = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;
                    if ($table !== 'shows' && Schema::hasColumn($table, 'show_id')) DB::table($table)->whereIn('show_id', $shows)->delete();
                }
                if ($shifts && Schema::hasTable('fulfillment_shift_shows')) DB::table('fulfillment_shift_shows')->whereIn('shift_id', $shifts)->delete();
                DB::table('fulfillment_shifts')->whereIn('id', $shifts)->delete();
                DB::table('shows')->whereIn('id', $shows)->delete();
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
        Setting::set(self::SETTING, '{}');
    }
}
