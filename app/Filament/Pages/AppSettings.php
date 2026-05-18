<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\OllamaService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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

    // Only admins can access settings
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getView(): string
    {
        return 'filament.pages.app-settings';
    }

    // ── Branding ──────────────────────────────────────────────────────────────

    public string $brand_name     = 'VortexOps';
    public string $primary_color  = '#7c3aed';
    public ?string $logo_path     = null;
    /** @var mixed */
    public $logo_upload            = null;

    // ── AI ────────────────────────────────────────────────────────────────────

    public bool   $ai_enabled      = true;
    public string $ollama_base_url = '';
    public string $ollama_model    = '';
    public int    $ollama_timeout  = 60;

    public function mount(): void
    {
        $this->brand_name     = Setting::get('brand_name',    'VortexOps');
        $this->primary_color  = Setting::get('primary_color', '#7c3aed');
        $this->logo_path      = Setting::get('logo_path');

        $this->ai_enabled      = Setting::getBool('ai_enabled', true);
        $this->ollama_base_url = Setting::get('ollama_base_url', config('ollama.base_url', 'http://localhost:11434'));
        $this->ollama_model    = Setting::get('ollama_model',    config('ollama.model',    'llama3.2'));
        $this->ollama_timeout  = (int) Setting::get('ollama_timeout', config('ollama.timeout', 60));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Changes')
                ->icon('heroicon-o-check')
                ->action('saveSettings'),

            Action::make('test_ollama')
                ->label('Test Ollama')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action('testOllamaConnection'),
        ];
    }

    public function saveSettings(): void
    {
        $this->validate([
            'brand_name'      => 'required|string|max:60',
            'primary_color'   => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo_upload'     => 'nullable|image|max:2048',
            'ollama_base_url' => 'required|url',
            'ollama_model'    => 'required|string|max:100',
            'ollama_timeout'  => 'required|integer|min:5|max:300',
        ]);

        // Handle logo upload
        if ($this->logo_upload) {
            $path = $this->logo_upload->store('brand', 'public');
            Setting::set('logo_path', $path);
            $this->logo_path    = $path;
            $this->logo_upload  = null;
        }

        Setting::set('brand_name',     $this->brand_name);
        Setting::set('primary_color',  $this->primary_color);
        Setting::set('ai_enabled',     $this->ai_enabled ? 'true' : 'false');
        Setting::set('ollama_base_url', $this->ollama_base_url);
        Setting::set('ollama_model',    $this->ollama_model);
        Setting::set('ollama_timeout',  (string) $this->ollama_timeout);

        Notification::make()
            ->title('Settings saved')
            ->body('Branding changes take effect on the next page load.')
            ->success()
            ->send();
    }

    public function removeLogo(): void
    {
        Setting::set('logo_path', '');
        $this->logo_path = null;

        Notification::make()->title('Logo removed')->success()->send();
    }

    public function testOllamaConnection(): void
    {
        $service = app(OllamaService::class);

        if ($service->isAvailable()) {
            $models = $service->availableModels();
            Notification::make()
                ->title('Ollama is online')
                ->body('Available models: ' . (count($models) ? implode(', ', $models) : 'none pulled yet'))
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Ollama is offline')
                ->body('Could not connect to ' . $this->ollama_base_url . '. Run: ollama serve')
                ->danger()
                ->send();
        }
    }
}
