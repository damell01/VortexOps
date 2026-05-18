<?php

namespace App\Livewire;

use App\Models\Setting;
use App\Services\OllamaService;
use Illuminate\View\View;
use Livewire\Component;

class AiChatPanel extends Component
{
    public bool   $isOpen      = false;
    public string $question    = '';
    public array  $messages    = [];
    public bool   $isLoading   = false;
    public array  $pageContext = [];
    public string $contextLabel = 'VortexOps';
    public bool   $ollamaOnline = false;

    public function mount(): void
    {
        $this->refreshContext();
    }

    public function toggle(): void
    {
        $this->isOpen = ! $this->isOpen;
        if ($this->isOpen) {
            $this->refreshContext();
        }
    }

    public function refreshContext(): void
    {
        $service            = app(OllamaService::class);
        $ctx                = $service->detectPageContext(request()->path());
        $this->pageContext   = $ctx;
        $this->contextLabel = $ctx['page_title'] ?? 'VortexOps';
        $this->ollamaOnline = $service->isAvailable();
    }

    public function sendMessage(): void
    {
        $q = trim($this->question);
        if ($q === '' || $this->isLoading) {
            return;
        }

        $this->question    = '';
        $this->isLoading   = true;
        $this->messages[]  = ['role' => 'user', 'content' => $q, 'time' => now()->format('H:i')];

        try {
            $service = app(OllamaService::class);

            // Inject current page context into the question
            $contextNote = $this->buildContextNote();
            $fullQuestion = $contextNote ? "{$contextNote}\n\nUser question: {$q}" : $q;

            $log = $service->askQuestion($fullQuestion);

            $this->messages[] = [
                'role'    => 'assistant',
                'content' => $log->success ? $log->response : 'Error: ' . $log->error_message,
                'time'    => now()->format('H:i'),
                'latency' => $log->latency_ms,
                'success' => $log->success,
            ];
        } catch (\Exception $e) {
            $this->messages[] = [
                'role'    => 'assistant',
                'content' => 'Could not reach Ollama: ' . $e->getMessage(),
                'time'    => now()->format('H:i'),
                'success' => false,
            ];
        } finally {
            $this->isLoading = false;
        }

        $this->dispatch('panelScrollToBottom');
    }

    public function clearChat(): void
    {
        $this->messages = [];
    }

    private function buildContextNote(): string
    {
        if (empty($this->pageContext) || ($this->pageContext['page_type'] ?? 'general') === 'general') {
            return '';
        }

        return '[Context: The user is currently viewing the '
            . ($this->pageContext['page_title'] ?? 'admin panel')
            . ' page. Relevant data: '
            . json_encode($this->pageContext, JSON_UNESCAPED_SLASHES)
            . ']';
    }

    public function render(): View
    {
        return view('livewire.ai-chat-panel');
    }
}
