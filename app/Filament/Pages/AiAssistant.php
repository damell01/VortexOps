<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\OllamaService;
use Filament\Actions\Action;
use Filament\Pages\Page;

class AiAssistant extends Page
{
    protected static ?string $title = 'Vortex Assistant';

    public function getView(): string
    {
        return 'filament.pages.ai-assistant';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'AI';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getNavigationLabel(): string
    {
        return 'Vortex Assistant';
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-sparkles';
    }

    public string $question    = '';
    public array  $messages    = [];
    public bool   $isLoading   = false;
    public string $ollamaModel = '';
    public string $ollamaBaseUrl = '';
    public bool   $ollamaOnline = false;
    public array $availableModels = [];

    public function mount(): void
    {
        $this->refreshStatus();
    }

    public function refreshStatus(): void
    {
        $service = app(OllamaService::class);

        $this->ollamaOnline = $service->isAvailable();
        $this->ollamaBaseUrl = Setting::get('ollama_base_url', $service->currentBaseUrl());
        $this->availableModels = $service->availableModels();
        $this->ollamaModel = Setting::get('ollama_model', $service->currentModel());

        if ($this->ollamaModel === 'llama3.2' && in_array('llama3.2:3b', $this->availableModels, true)) {
            $this->ollamaModel = 'llama3.2:3b';
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clear')
                ->label('Clear Chat')
                ->icon('heroicon-o-trash')
                ->color('gray')
                ->action(fn () => $this->messages = []),

            Action::make('refresh_status')
                ->label('Check Ollama')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->refreshStatus()),
        ];
    }

    public function sendMessage(): void
    {
        $q = trim($this->question);
        if ($q === '' || $this->isLoading) {
            return;
        }

        $this->question  = '';
        $this->isLoading = true;
        $this->messages[] = ['role' => 'user', 'content' => $q, 'time' => now()->format('g:i A')];

        try {
            $log = app(OllamaService::class)->askQuestion($q);
            $this->appendAiMessage($log);
        } catch (\Exception $e) {
            $this->appendError($e->getMessage());
        } finally {
            $this->isLoading = false;
        }

        $this->dispatch('scrollToBottom');
    }

    public function runQuickAction(string $action): void
    {
        if ($this->isLoading) {
            return;
        }

        $label = match ($action) {
            'inventory_analysis' => 'Run inventory health analysis',
            'reorder_suggestions' => 'Generate reorder suggestions',
            'movement_analysis'  => 'Analyse recent movement patterns',
            default               => $action,
        };

        $this->isLoading  = true;
        $this->messages[] = ['role' => 'user', 'content' => $label, 'time' => now()->format('g:i A')];

        try {
            $service = app(OllamaService::class);
            $log = match ($action) {
                'inventory_analysis'  => $service->inventoryAnalysis(),
                'reorder_suggestions' => $service->reorderSuggestions(),
                'movement_analysis'   => $service->movementAnalysis(),
                default               => $service->askQuestion($action),
            };
            $this->appendAiMessage($log);
        } catch (\Exception $e) {
            $this->appendError($e->getMessage());
        } finally {
            $this->isLoading = false;
        }

        $this->dispatch('scrollToBottom');
    }

    private function appendAiMessage(\App\Models\AiLog $log): void
    {
        $this->messages[] = [
            'role'      => 'assistant',
            'content'   => $log->success ? $log->response : ('Error: ' . $log->error_message),
            'time'      => now()->format('g:i A'),
            'latency'   => $log->latency_ms,
            'success'   => $log->success,
        ];
    }

    private function appendError(string $msg): void
    {
        $this->messages[] = [
            'role'    => 'assistant',
            'content' => 'Could not reach Ollama: ' . $msg,
            'time'    => now()->format('g:i A'),
            'success' => false,
        ];
    }
}
