<?php

namespace App\Livewire;

use App\Services\AiContextBuilder;
use App\Support\AdminModules;
use Illuminate\Support\Facades\Http;
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

        try {
            $systemPrompt = app(AiContextBuilder::class)->buildSystemPrompt($this->currentPath);
            $fullReply    = $this->streamOllama($systemPrompt);
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

    private function streamOllama(string $systemPrompt): string
    {
        $baseUrl = config('services.ollama.url', env('OLLAMA_BASE_URL', 'http://ollama:11434'));
        $model   = config('services.ollama.model', env('OLLAMA_MODEL', 'llama3.2:3b'));

        // Keep last 10 turns (user+assistant pairs) to cap prompt size
        $history = array_slice($this->messages, -10);

        $apiMessages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $history,
        );

        $response = Http::withOptions(['stream' => true])
            ->timeout(120)
            ->post("{$baseUrl}/api/chat", [
                'model'    => $model,
                'messages' => $apiMessages,
                'stream'   => true,
                'options'  => ['num_ctx' => 2048],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Ollama HTTP {$response->status()}");
        }

        $body        = $response->getBody();
        $fullContent = '';

        while (! $body->eof()) {
            $line = $this->readLine($body);
            if ($line === '') {
                continue;
            }

            $data  = json_decode($line, true);
            $chunk = $data['message']['content'] ?? '';

            if ($chunk !== '') {
                $fullContent .= $chunk;
                $this->stream(to: 'streamingResponse', content: $chunk, replace: false);
            }

            if ($data['done'] ?? false) {
                break;
            }
        }

        return $fullContent ?: '(no response)';
    }

    private function readLine(\Psr\Http\Message\StreamInterface $stream): string
    {
        $line = '';
        while (! $stream->eof()) {
            $char = $stream->read(1);
            if ($char === "\n") {
                break;
            }
            $line .= $char;
        }
        return trim($line);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.ai-chat-panel', [
            'aiEnabled' => AdminModules::isEnabled('ai'),
        ]);
    }
}
