<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use App\Support\AdminModules;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Livewire\WithFileUploads;

class AppSettings extends Page
{
    use WithFileUploads;

    protected static ?string $title = 'Settings';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Settings';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-cog-6-tooth';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() || $user?->isOwner()) ?? false;
    }

    public function getView(): string
    {
        return 'filament.pages.app-settings';
    }

    // ── Branding ──────────────────────────────────────────────────────────────

    public string  $brand_name    = 'VortexOps';
    public string  $primary_color = '#7c3aed';
    public ?string $logo_path     = null;
    /** @var mixed */
    public $logo_upload = null;

    // ── Show Import ──────────────────────────────────────────────────────────

    public string $show_import_mode                = 'manual';
    public string $show_ready_notification_email   = '';

    // ── Whatnot connection test ──────────────────────────────────────────────

    public string $whatnotTestResult = '';
    public string $whatnotTestStatus = ''; // 'success' | 'error' | ''

    // ── Whatnot import ───────────────────────────────────────────────────────

    public string $whatnotImportResult = '';
    public string $whatnotImportStatus = ''; // 'success' | 'error' | ''
    public string $whatnotLastImport   = '';

    // ── Shipping Surcharge ───────────────────────────────────────────────────

    public string $shipping_surcharge_rate      = '4.00';
    public string $shipping_surcharge_threshold = '500.00';

    // ── AI / Ollama ──────────────────────────────────────────────────────────

    public string $ollama_base_url   = '';
    public string $ollama_model      = '';
    public string $ollamaTestResult  = '';
    public string $ollamaTestStatus  = ''; // 'success' | 'error' | ''

    // ── Demo / Showcase Mode ─────────────────────────────────────────────────

    public bool $demo_mode = false;

    // ── Maintenance ──────────────────────────────────────────────────────────

    public string $lastCommandOutput = '';
    public string $backupResult      = '';
    public string $backupStatus      = ''; // 'success' | 'error' | ''

    // ── Notifications ────────────────────────────────────────────────────────

    public string $notify_low_stock_mode       = 'all';
    public array  $notify_low_stock_users      = [];
    public string $notify_damaged_mode         = 'all';
    public array  $notify_damaged_users        = [];
    public string $notify_show_ready_mode      = 'admins';
    public array  $notify_show_ready_users     = [];
    public string $notify_show_reconciled_mode  = 'admins';
    public array  $notify_show_reconciled_users = [];
    public array  $enabled_modules  = [];
    public array  $enabled_features = [];

    public function mount(): void
    {
        $this->brand_name    = Setting::get('brand_name',    'VortexOps');
        $this->primary_color = Setting::get('primary_color', '#7c3aed');
        $this->logo_path     = Setting::get('logo_path');

        $this->show_import_mode              = Setting::get('show_import_mode', 'manual');
        $this->show_ready_notification_email = Setting::get('show_ready_notification_email', '');
        $this->whatnotLastImport             = Setting::get('whatnot_last_import_at', '');

        $this->notify_low_stock_mode        = Setting::get('notify_low_stock_mode', 'all');
        $this->notify_low_stock_users       = json_decode(Setting::get('notify_low_stock_users', '[]'), true) ?? [];
        $this->notify_damaged_mode          = Setting::get('notify_damaged_mode', 'all');
        $this->notify_damaged_users         = json_decode(Setting::get('notify_damaged_users', '[]'), true) ?? [];
        $this->notify_show_ready_mode       = Setting::get('notify_show_ready_mode', 'admins');
        $this->notify_show_ready_users      = json_decode(Setting::get('notify_show_ready_users', '[]'), true) ?? [];
        $this->notify_show_reconciled_mode  = Setting::get('notify_show_reconciled_mode', 'admins');
        $this->notify_show_reconciled_users = json_decode(Setting::get('notify_show_reconciled_users', '[]'), true) ?? [];
        $this->enabled_modules  = AdminModules::enabledSlugs();
        $this->enabled_features = AdminModules::enabledFeatures();

        $this->demo_mode = (bool) Setting::get('demo_mode', false);

        $this->shipping_surcharge_rate      = Setting::get('shipping_surcharge_rate', '4.00');
        $this->shipping_surcharge_threshold = Setting::get('shipping_surcharge_threshold', '500.00');

        $this->ollama_base_url = Setting::get('ollama_base_url', config('services.ollama.url', 'http://localhost:11434'));
        $this->ollama_model    = Setting::get('ollama_model',    config('services.ollama.model', 'llama3.2:3b'));
    }

    public function getAllUsersProperty(): \Illuminate\Support\Collection
    {
        return User::orderBy('name')->get()->mapWithKeys(fn ($u) => [$u->id => $u->name . ' (' . $u->email . ')']);
    }

    /**
     * @return array<string, array{label: string, description: string, group: string, order: int}>
     */
    public function getAvailableModulesProperty(): array
    {
        return AdminModules::definitions();
    }

    public function getCanSeeModuleTogglesProperty(): bool
    {
        $user = auth()->user();
        return ($user?->isOwner() || $user?->isSuperAdmin()) ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Changes')
                ->icon('heroicon-o-check')
                ->action('saveSettings'),
        ];
    }

    public function saveSettings(): void
    {
        $isOwner = auth()->user()?->isOwner() ?? false;

        if ($isOwner) {
            $this->enabled_modules = AdminModules::normalizeEnabledSlugs($this->enabled_modules);
        }

        $rules = [
            'brand_name'                       => 'required|string|max:60',
            'primary_color'                    => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo_upload'                      => 'nullable|image|max:2048',
            'show_import_mode'                 => 'required|in:manual,auto_whatnot',
            'show_ready_notification_email'    => 'nullable|email|max:255',
            'notify_low_stock_mode'            => 'required|in:all,admins,custom',
            'notify_low_stock_users'           => 'nullable|array',
            'notify_low_stock_users.*'         => 'integer|exists:users,id',
            'notify_damaged_mode'              => 'required|in:all,admins,custom',
            'notify_damaged_users'             => 'nullable|array',
            'notify_damaged_users.*'           => 'integer|exists:users,id',
            'notify_show_ready_mode'           => 'required|in:all,admins,custom',
            'notify_show_ready_users'          => 'nullable|array',
            'notify_show_ready_users.*'        => 'integer|exists:users,id',
            'notify_show_reconciled_mode'      => 'required|in:all,admins,custom',
            'notify_show_reconciled_users'     => 'nullable|array',
            'notify_show_reconciled_users.*'   => 'integer|exists:users,id',
            'shipping_surcharge_rate'          => 'required|numeric|min:0',
            'shipping_surcharge_threshold'     => 'required|numeric|min:0',
        ];

        if ($isOwner) {
            $rules['enabled_modules']    = 'required|array|min:1';
            $rules['enabled_modules.*']  = 'in:' . implode(',', array_keys(AdminModules::definitions()));
            $rules['enabled_features']   = 'nullable|array';
            $rules['enabled_features.*'] = 'in:' . implode(',', array_keys(AdminModules::featureDefinitions()));
        }

        $this->validate($rules);

        if ($this->logo_upload) {
            $path = $this->logo_upload->store('brand', 'public');
            Setting::set('logo_path', $path);
            $this->logo_path   = $path;
            $this->logo_upload = null;
        }

        Setting::set('brand_name',    $this->brand_name);
        Setting::set('primary_color', $this->primary_color);
        Setting::set('show_import_mode', $this->show_import_mode);
        Setting::set('show_ready_notification_email', $this->show_ready_notification_email);

        Setting::set('notify_low_stock_mode',        $this->notify_low_stock_mode);
        Setting::set('notify_low_stock_users',        json_encode($this->notify_low_stock_users));
        Setting::set('notify_damaged_mode',           $this->notify_damaged_mode);
        Setting::set('notify_damaged_users',          json_encode($this->notify_damaged_users));
        Setting::set('notify_show_ready_mode',        $this->notify_show_ready_mode);
        Setting::set('notify_show_ready_users',       json_encode($this->notify_show_ready_users));
        Setting::set('notify_show_reconciled_mode',   $this->notify_show_reconciled_mode);
        Setting::set('notify_show_reconciled_users',  json_encode($this->notify_show_reconciled_users));

        Setting::set('shipping_surcharge_rate',      $this->shipping_surcharge_rate);
        Setting::set('shipping_surcharge_threshold', $this->shipping_surcharge_threshold);

        Setting::set('ollama_base_url', rtrim(trim($this->ollama_base_url), '/') ?: 'http://localhost:11434');
        Setting::set('ollama_model',    trim($this->ollama_model) ?: 'llama3.2:3b');
        if (auth()->user()?->isSuperAdmin() || auth()->user()?->isOwner()) {
            Setting::set('demo_mode', $this->demo_mode ? '1' : '0');
        }

        if ($isOwner) {
            Setting::set('enabled_admin_modules',  json_encode(AdminModules::normalizeEnabledSlugs($this->enabled_modules)));
            Setting::set('enabled_admin_features', json_encode(array_values(array_intersect(
                array_keys(AdminModules::featureDefinitions()),
                $this->enabled_features
            ))));
            AdminModules::flushMemo();
        }

        Notification::make()
            ->title('Settings saved')
            ->body('Changes take effect on the next page load.')
            ->success()
            ->send();
    }

    public function removeLogo(): void
    {
        Setting::set('logo_path', '');
        $this->logo_path = null;

        Notification::make()->title('Logo removed')->success()->send();
    }

    public function getWhatnotConfiguredProperty(): bool
    {
        return ! empty(config('vortex.whatnot.email')) && ! empty(config('vortex.whatnot.password'));
    }

    public function testWhatnotConnection(): void
    {
        $this->whatnotTestResult = '';
        $this->whatnotTestStatus = '';

        try {
            $result = app(WhatnotScraper::class)->testConnection();

            $this->whatnotTestResult = "Connected as {$result['email']}. Seller dashboard accessible.";
            $this->whatnotTestStatus = 'success';

            Notification::make()
                ->title('Whatnot connection OK')
                ->body($this->whatnotTestResult)
                ->success()
                ->send();
        } catch (\RuntimeException $e) {
            $this->whatnotTestResult = $e->getMessage();
            $this->whatnotTestStatus = 'error';

            Notification::make()
                ->title('Whatnot connection failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function testOllamaConnection(): void
    {
        $this->ollamaTestResult = '';
        $this->ollamaTestStatus = '';

        $url = rtrim(trim($this->ollama_base_url) ?: 'http://localhost:11434', '/');

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)->get("{$url}/api/tags");

            if (! $response->successful()) {
                throw new \RuntimeException("HTTP {$response->status()}: {$response->body()}");
            }

            $models = collect($response->json('models', []))
                ->pluck('name')
                ->filter()
                ->values()
                ->toArray();

            if (empty($models)) {
                $this->ollamaTestResult = 'Connected — no models found. Pull a model with: ollama pull llama3.2';
            } else {
                $this->ollamaTestResult = 'Connected. Available models: ' . implode(', ', $models);
            }
            $this->ollamaTestStatus = 'success';

            Notification::make()
                ->title('Ollama connected')
                ->body($this->ollamaTestResult)
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->ollamaTestResult = 'Connection failed: ' . $e->getMessage();
            $this->ollamaTestStatus = 'error';

            Notification::make()
                ->title('Ollama connection failed')
                ->body($this->ollamaTestResult)
                ->danger()
                ->send();
        }
    }

    public function importWhatnotShows(): void
    {
        $this->whatnotImportResult = '';
        $this->whatnotImportStatus = '';

        try {
            $result = app(WhatnotScraper::class)->importAllEnabledChannels(
                limit: (int) config('vortex.whatnot.limit', 50),
            );

            $channelNote = $result['channels'] > 1 ? " across {$result['channels']} channels" : '';
            $this->whatnotImportResult = "Import complete — {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped{$channelNote}.";
            $this->whatnotImportStatus = 'success';

            $this->whatnotLastImport = now()->toDateTimeString();
            Setting::set('whatnot_last_import_at', $this->whatnotLastImport);

            Notification::make()
                ->title('Whatnot import complete')
                ->body($this->whatnotImportResult)
                ->success()
                ->send();
        } catch (\RuntimeException $e) {
            $this->whatnotImportResult = $e->getMessage();
            $this->whatnotImportStatus = 'error';

            Notification::make()
                ->title('Whatnot import failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function runMigrations(): void
    {
        try {
            $exitCode = Artisan::call('migrate', ['--force' => true]);
            $output   = trim(Artisan::output());

            $this->lastCommandOutput = $output ?: 'Nothing to migrate — database is already up to date.';

            $exitCode === 0
                ? Notification::make()->title('Migrations completed')->body($this->lastCommandOutput)->success()->send()
                : Notification::make()->title('Migration failed')->body($this->lastCommandOutput)->danger()->send();
        } catch (\Throwable $e) {
            $this->lastCommandOutput = $e->getMessage();
            Notification::make()->title('Migration error')->body($e->getMessage())->danger()->send();
        }
    }

    public function optimizeApp(): void
    {
        try {
            Artisan::call('optimize');
            $out = trim(Artisan::output());
            Artisan::call('filament:optimize');
            $out .= "\n" . trim(Artisan::output());

            $this->lastCommandOutput = trim($out);

            Notification::make()
                ->title('Application optimized')
                ->body('Config, routes, views, and Filament components are now cached.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->lastCommandOutput = $e->getMessage();
            Notification::make()->title('Optimize failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function runBackup(): void
    {
        $this->backupResult = '';
        $this->backupStatus = '';

        try {
            $exitCode = Artisan::call('db:backup');
            $output   = trim(Artisan::output());

            $this->backupResult = $output ?: 'Backup complete.';
            $this->backupStatus = $exitCode === 0 ? 'success' : 'error';

            $exitCode === 0
                ? Notification::make()->title('Backup complete')->body($this->backupResult)->success()->send()
                : Notification::make()->title('Backup failed')->body($this->backupResult)->danger()->send();
        } catch (\Throwable $e) {
            $this->backupResult = $e->getMessage();
            $this->backupStatus = 'error';
            Notification::make()->title('Backup error')->body($e->getMessage())->danger()->send();
        }
    }

    public function clearCaches(): void
    {
        try {
            Artisan::call('optimize:clear');
            $out = trim(Artisan::output());
            Artisan::call('filament:optimize-clear');
            $out .= "\n" . trim(Artisan::output());

            $this->lastCommandOutput = trim($out);

            Notification::make()
                ->title('Caches cleared')
                ->body('All config, route, view, and Filament caches have been flushed.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->lastCommandOutput = $e->getMessage();
            Notification::make()->title('Cache clear failed')->body($e->getMessage())->danger()->send();
        }
    }
}
