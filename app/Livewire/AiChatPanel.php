<?php

namespace App\Livewire;

use App\Services\OllamaService;
use Illuminate\View\View;
use Livewire\Component;

class AiChatPanel extends Component
{
    public bool   $isOpen       = false;
    public string $question     = '';
    public array  $messages     = [];
    public array  $pageContext  = [];
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
        if ($q === '') {
            return;
        }

        $this->question   = '';
        $this->messages[] = ['role' => 'user', 'content' => $q, 'time' => now()->format('g:i A')];

        $service     = app(OllamaService::class);
        $contextNote = $this->buildContextNote();
        $fullQuestion = $contextNote ? "{$contextNote}\n\nUser question: {$q}" : $q;
        $systemText  = $this->buildSystemText();

        try {
            $log = $service->streamQuestion($fullQuestion, $systemText, function (string $token): void {
                $this->stream(to: 'aiStream', value: $token, replace: false);
            });

            $this->messages[] = [
                'role'    => 'assistant',
                'content' => $log->success
                    ? ($log->response ?: '(empty response)')
                    : 'Error: ' . $log->error_message,
                'time'    => now()->format('g:i A'),
                'latency' => $log->latency_ms,
                'success' => $log->success,
            ];
        } catch (\Exception $e) {
            $this->messages[] = [
                'role'    => 'assistant',
                'content' => 'Could not reach Ollama: ' . $e->getMessage(),
                'time'    => now()->format('g:i A'),
                'success' => false,
            ];
        }

        $this->dispatch('message-received');
        $this->dispatch('panelScrollToBottom');
    }

    public function clearChat(): void
    {
        $this->messages = [];
    }

    private function buildSystemText(): string
    {
        $context = app(OllamaService::class)->buildInventoryContext();

        return "You are VortexOps AI, an inventory assistant for Vortex Breaks — a sports card break business that streams on Whatnot. "
            . "You have access to the current inventory snapshot below. Answer questions concisely and accurately. "
            . "Use plain text or bullet points. Do not use markdown headers.\n\n"
            . "Current inventory snapshot:\n"
            . json_encode($context, JSON_PRETTY_PRINT);
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
