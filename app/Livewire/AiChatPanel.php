<?php

namespace App\Livewire;

use App\Services\AiContextBuilder;
use App\Support\AdminModules;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class AiChatPanel extends Component
{
    public string $input       = '';
    public bool   $thinking    = false;
    public string $currentPath = '';

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

        $this->input    = '';
        $this->thinking = true;

        $this->messages[] = ['role' => 'user', 'content' => $text];
        session(['vortex_ai_messages' => $this->messages]);

        try {
            $systemPrompt = app(AiContextBuilder::class)->buildSystemPrompt($this->currentPath);
            $reply        = $this->callOllama($systemPrompt, $text);
        } catch (\Throwable $e) {
            Log::error('AiChatPanel Ollama error', ['error' => $e->getMessage()]);
            $reply = 'Sorry, I couldn\'t reach the AI backend right now. Check that Ollama is running.';
        }

        $this->messages[] = ['role' => 'assistant', 'content' => $reply];
        session(['vortex_ai_messages' => $this->messages]);

        $this->thinking = false;
        $this->dispatch('ai-scroll-bottom');
    }

    public function clear(): void
    {
        $this->messages = [];
        session()->forget('vortex_ai_messages');
    }

    private function callOllama(string $systemPrompt, string $userMessage): string
    {
        $baseUrl = config('services.ollama.url', env('OLLAMA_BASE_URL', 'http://ollama:11434'));
        $model   = config('services.ollama.model', env('OLLAMA_MODEL', 'llama3.2:3b'));

        $history = collect($this->messages)
            ->filter(fn ($m) => $m['role'] !== 'assistant' || $m !== end($this->messages))
            ->values()
            ->all();

        // Build full message list for the API (system + history + current user message)
        $apiMessages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            array_slice($history, -20), // last 20 messages to keep context bounded
            [['role' => 'user', 'content' => $userMessage]],
        );

        $response = Http::timeout(120)->post("{$baseUrl}/api/chat", [
            'model'    => $model,
            'messages' => $apiMessages,
            'stream'   => false,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Ollama returned HTTP {$response->status()}");
        }

        return trim($response->json('message.content', '(no response)'));
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.ai-chat-panel', [
            'aiEnabled' => AdminModules::isEnabled('ai'),
        ]);
    }
}
