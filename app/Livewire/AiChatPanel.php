<?php

namespace App\Livewire;

use App\AI\Services\AssistantService;
use App\Support\AdminModules;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class AiChatPanel extends Component
{
    public string $input             = '';
    public bool   $thinking          = false;
    public string $currentPath       = '';
    public string $streamingResponse = '';

    /** @var list<array{role: string, content: string}> */
    public array $messages = [];

    public function mount(): void
    {
        $this->messages = session('vortex_ai_messages', []);
    }

    public function setPath(string $path): void
    {
        $this->currentPath = $path;
    }

    public function send(): void
    {
        $text = trim($this->input);
        if ($text === '' || $this->thinking) {
            return;
        }

        $this->input             = '';
        $this->thinking          = true;
        $this->streamingResponse = '';

        $this->messages[] = ['role' => 'user', 'content' => $text];
        session(['vortex_ai_messages' => $this->messages]);

        $fullReply = '';

        try {
            $history   = array_slice($this->messages, -10);
            // The assistant routes data questions to tools and falls back to
            // plain chat — all of that lives in AssistantService, not here.
            $generator = app(AssistantService::class)->stream($this->currentPath, $history, $text, auth()->user());

            foreach ($generator as $chunk) {
                $fullReply .= $chunk;
                $this->stream(to: 'streamingResponse', content: $chunk, replace: false);
            }

            if ($fullReply === '') {
                $fullReply = '(no response)';
            }
        } catch (\Throwable $e) {
            Log::error('AiChatPanel error', ['error' => $e->getMessage()]);
            $fullReply = 'Could not reach the AI backend — check that Ollama is running.';
            $this->stream(to: 'streamingResponse', content: $fullReply, replace: true);
        }

        $this->messages[]        = ['role' => 'assistant', 'content' => $fullReply];
        $this->streamingResponse = '';
        $this->thinking          = false;

        session(['vortex_ai_messages' => $this->messages]);
        $this->dispatch('ai-scroll-bottom');
    }

    public function clear(): void
    {
        $this->messages          = [];
        $this->streamingResponse = '';
        session()->forget('vortex_ai_messages');
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.ai-chat-panel', [
            'aiEnabled' => AdminModules::isEnabled('ai'),
        ]);
    }
}
