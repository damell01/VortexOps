<?php

namespace App\Console\Commands;

use App\Filament\Resources\RoleResource;
use App\Models\User;
use App\Support\AdminModules;
use App\Support\NavVisibility;
use Filament\Facades\Filament;
use Illuminate\Console\Command;

/**
 * Why a page 403s for a given user, on the machine it is actually happening on.
 *
 * Four separate rules can close a page — the per-role visibility list, a
 * switched-off module, the page's own canAccess(), and the owner/developer
 * checks — and all four render the same Access Denied screen. Worse, a stale
 * config or route cache can make a deployment behave like code that is no
 * longer there, which no amount of reading the source will reveal. This reports
 * the first rule that says no for each page, and the state of everything that
 * decides it, so a report of "it 403s" becomes an answer rather than an
 * investigation.
 */
class DiagnoseAccess extends Command
{
    protected $signature = 'vortex:why-403
        {email? : The account to test as — defaults to the configured owner}
        {--denied-only : Only list pages that are refused}';

    protected $description = 'Report exactly which rule denies each admin page for a user';

    public function handle(): int
    {
        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);

        $email = $this->argument('email') ?: config('app.owner_email');
        $user  = User::firstWhere('email', $email);

        if (! $user) {
            $this->error("No user with the email {$email}.");
            $this->line('  Known accounts: ' . User::query()->pluck('email')->implode(', '));

            return self::FAILURE;
        }

        auth()->setUser($user);

        $this->environmentReport($user);
        $this->line('');

        return $this->pageReport($user);
    }

    /**
     * The things that make a deployment behave unlike its source.
     *
     * A cached config or route table is the usual reason a fix that is
     * demonstrably in the code has no effect on the running site.
     */
    private function environmentReport(User $user): void
    {
        $this->line('');
        $this->line('  <fg=cyan;options=bold>VortexOps access diagnosis</>');
        $this->line('  <fg=gray>' . now()->toDateTimeString() . '</>');
        $this->line('');

        $cached = [
            'config' => file_exists($this->laravel->getCachedConfigPath()),
            'routes' => file_exists($this->laravel->getCachedRoutesPath()),
            'events' => file_exists($this->laravel->getCachedEventsPath()),
        ];

        $stale = array_keys(array_filter($cached));

        $this->line('  <options=bold>Environment</>');
        $this->line('    env            ' . config('app.env') . '   debug: ' . var_export(config('app.debug'), true));
        $this->line('    commit         ' . $this->commit());
        $this->line('    caches         ' . ($stale === [] ? '<fg=green>none</>' : '<fg=yellow>' . implode(', ', $stale) . ' cached</>'));

        if ($stale !== []) {
            $this->line('    <fg=yellow>↳ run `php artisan optimize:clear` — a cached config or route table can keep</>');
            $this->line('    <fg=yellow>  serving behaviour from code that is no longer deployed.</>');
        }

        $this->line('');
        $this->line('  <options=bold>Account</>');
        $this->line('    email          ' . $user->email);
        $this->line('    roles          ' . ($user->getRoleNames()->implode(', ') ?: '<fg=yellow>none assigned</>'));
        $this->line('    owner          ' . ($user->isOwner()
            ? '<fg=green>yes — exempt from the visibility gate</>'
            : 'no   (owner is ' . (config('app.owner_email') ?: 'dbellcreations@gmail.com') . ')'));

        foreach ($user->getRoleNames() as $role) {
            $this->line('    ' . str_pad($role, 14) . ' ' . (NavVisibility::hasExplicitVisibility($role)
                ? count(NavVisibility::visibleForRole($role)) . ' pages granted'
                : '<fg=gray>never configured — falls back to the hide list</>'));
        }

        $this->line('');
        $this->line('  <options=bold>Modules</>');
        $enabled = AdminModules::enabledSlugs();
        $off     = array_values(array_diff(array_keys(AdminModules::definitions()), $enabled));
        $this->line('    on             ' . (implode(', ', $enabled) ?: 'none'));
        $this->line('    off            ' . ($off === [] ? 'none' : '<fg=yellow>' . implode(', ', $off) . '</>'));

        if ($off !== []) {
            $this->line('    <fg=yellow>↳ a switched-off module closes its pages for everyone, owner included,</>');
            $this->line('    <fg=yellow>  whatever the Roles screen shows.</>');
        }
    }

    private function pageReport(User $user): int
    {
        $rows       = [];
        $deniedOnly = $this->option('denied-only');
        $unexplained = 0;

        foreach (RoleResource::pageOptions() as $class => $label) {
            $reason = $this->firstBlockingReason($class, $user);

            if ($reason === null && $deniedOnly) {
                continue;
            }

            // The case worth shouting about: ticked on the Roles screen and
            // refused anyway, by something the screen never mentions.
            $ticked = ! NavVisibility::isHiddenForUser($class, $user);

            if ($reason !== null && $ticked) {
                $unexplained++;
            }

            $rows[] = [
                $label,
                class_basename($class),
                $reason === null ? '<fg=green>opens</>' : "<fg=red>{$reason}</>",
            ];
        }

        $this->table(['Page', 'Class', 'Verdict'], $rows);

        // Not pages, and not grantable — but the two things whose failure makes
        // the panel unusable, so they are checked rather than assumed.
        $this->line('  <options=bold>Not role-controlled</>');
        foreach (['/admin' => 'dashboard', '/admin/profile' => 'profile', '/admin/logout' => 'logout (POST)'] as $path => $what) {
            $this->line('    ' . str_pad($what, 16) . $path);
        }
        $this->line('    <fg=gray>These bypass the visibility gate entirely. If one of them 403s, the cause</>');
        $this->line('    <fg=gray>is not role visibility — check the caches above first.</>');
        $this->line('');

        if ($unexplained > 0) {
            $this->warn("  {$unexplained} page(s) are granted to this user and still refused. That is a bug — report the list above.");

            return self::FAILURE;
        }

        $this->info('  Nothing is refused that this user was granted.');

        return self::SUCCESS;
    }

    /** The first rule that closes this page, or null if it opens. */
    private function firstBlockingReason(string $class, User $user): ?string
    {
        if ($module = RoleResource::disabledModuleFor($class)) {
            return "module '{$module}' is off";
        }

        if (NavVisibility::isHiddenForUser($class, $user)) {
            return 'not granted to this role';
        }

        try {
            if (method_exists($class, 'canAccess') && ! $class::canAccess()) {
                return 'the page\'s own rule (usually owner or admin)';
            }
        } catch (\Throwable $e) {
            return 'canAccess() threw: ' . $e->getMessage();
        }

        return null;
    }

    private function commit(): string
    {
        $head = base_path('.git/HEAD');

        if (! is_readable($head)) {
            return 'unknown (no .git — a built image)';
        }

        $ref = trim((string) file_get_contents($head));

        if (str_starts_with($ref, 'ref: ')) {
            $branch = substr($ref, 5);
            $sha    = @file_get_contents(base_path('.git/' . $branch));

            return trim((string) $sha) . '  on ' . basename($branch);
        }

        return $ref;
    }
}
