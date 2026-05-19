<?php

namespace App\Livewire;

use App\Jobs\RunAiQuery;
use App\Services\OllamaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
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
    public string $pendingKey   = '';
    public bool   $isThinking   = false;

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
        if ($q === '' || $this->isThinking) {
            return;
        }

        $this->question   = '';
        $this->messages[] = ['role' => 'user', 'content' => $q, 'time' => now()->format('g:i A')];

        $contextNote  = $this->buildContextNote();
        $fullQuestion = $contextNote ? "{$contextNote}\n\nUser question: {$q}" : $q;
        $systemText   = $this->buildSystemText();

        $this->pendingKey = (string) Str::uuid();
        $this->isThinking = true;

        RunAiQuery::dispatch($this->pendingKey, $fullQuestion, $systemText, auth()->id());

        $this->dispatch('panelScrollToBottom');
    }

    public function checkForAiResponse(): void
    {
        if (! $this->pendingKey || ! $this->isThinking) {
            return;
        }

        $result = Cache::get("ai_pending_{$this->pendingKey}");

        if ($result !== null) {
            $this->messages[] = [
                'role'    => 'assistant',
                'content' => $result['success']
                    ? ($result['content'] ?: '(empty response)')
                    : 'Error: ' . $result['error'],
                'time'    => $result['time'],
                'latency' => $result['latency'],
                'success' => $result['success'],
            ];

            Cache::forget("ai_pending_{$this->pendingKey}");
            $this->pendingKey = '';
            $this->isThinking = false;

            $this->dispatch('message-received');
            $this->dispatch('panelScrollToBottom');
        }
    }

    public function clearChat(): void
    {
        $this->messages   = [];
        $this->pendingKey = '';
        $this->isThinking = false;
    }

    private function buildSystemText(): string
    {
        $context = app(OllamaService::class)->buildProjectContext();

        return <<<PROMPT
You are VortexOps AI — the operations assistant for Vortex Breaks, a sports card break business that streams live on Whatnot.

BUSINESS OVERVIEW:
Vortex Breaks buys sports card products (boxes, cases) in bulk and streams card breaks on Whatnot. Customers pay to participate; streamers open packs on camera. The business tracks inventory, manages streamers, records shows, calculates payouts, and handles client review feedback through this admin system.

WHAT YOU CAN HELP WITH:

INVENTORY — Card products tracked by SKU/category across warehouse and streamer locations. You know current stock levels, low-stock alerts, recent movements, and reorder needs.

STREAMERS — Contractors/employees who host shows. Each has a payout type (profit_share, package, hourly, flat_rate, or custom_formula), optional owner fee deductions, and may carry outstanding loans (advances repaid from future payouts).

SHOWS — Whatnot streaming sessions imported via scraper. Shows have gross revenue, tips, units sold, and one or more streamers. They flow through: pending_ingestion → pending_review → pending_approval → reconciled → closed.

PAYOUTS — Calculated per show per streamer. Formula depends on payout type. Deductions include owner fee and loan repayment. Pay runs (WeeklyPayoutBatch) group payouts by week and are submitted to ADP for processing.

REVIEW MODE — Visual annotation tool for client/team feedback. Sessions group annotations by project round. Each item has a type (bug, suggestion, question, annotation) and status (open → in_progress → fixed → approved/rejected).

RESPONSE STYLE:
- Be concise and direct. Use bullet points for lists.
- For number questions, show the key figure then brief context.
- Do not use markdown headers (##). Plain text only.
- If data is missing from context, say so rather than guessing.

LIVE DATA SNAPSHOT:
PROMPT
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
