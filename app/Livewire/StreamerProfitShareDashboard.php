<?php

namespace App\Livewire;

use App\Models\ProfitSharePacket;
use App\Models\Streamer;
use App\Services\ProfitShareCalculationService;
use Livewire\Component;
use Livewire\Attributes\Validate;

class StreamerProfitShareDashboard extends Component
{
    public Streamer $streamer;
    public ?ProfitSharePacket $selectedPacket = null;
    public bool $showHistory = false;

    #[Validate('nullable|string|max:1000')]
    public ?string $notes = null;

    private ProfitShareCalculationService $calculationService;

    public function mount(Streamer $streamer): void
    {
        $mine = auth()->user()?->streamer;
        abort_unless($mine && (int) $mine->id === (int) $streamer->id, 403);

        $this->streamer = $mine->load('profitSharePackets');
        $this->calculationService = app(ProfitShareCalculationService::class);
    }

    private function ownPacket(ProfitSharePacket $packet): ProfitSharePacket
    {
        abort_unless((int) $packet->streamer_id === (int) $this->streamer->id, 403);
        return $packet;
    }

    public function selectPacket(ProfitSharePacket $packet): void
    {
        $this->selectedPacket = $this->ownPacket($packet);
    }

    public function submitPacket(ProfitSharePacket $packet): void
    {
        $packet = $this->ownPacket($packet);

        if ($packet->status !== 'pending_review') {
            session()->flash('error', 'Only finalized packets can be submitted for approval');
            return;
        }

        $packet->submit();
        session()->flash('success', '✓ Packet submitted to manager for final approval');
        $this->selectedPacket = null;
        $this->dispatch('refresh');
    }

    public function addNotes(ProfitSharePacket $packet): void
    {
        $packet = $this->ownPacket($packet);
        $this->validate(['notes' => 'nullable|string|max:1000']);

        $packet->update(['notes' => $this->notes]);
        session()->flash('success', '✓ Notes saved');
        $this->notes = null;
    }

    public function getCurrentMonthPacket(): ?ProfitSharePacket
    {
        $now = now();
        $packet = $this->calculationService->getOrCreatePacket(
            $this->streamer,
            $now->year,
            $now->month
        );

        $this->calculationService->updatePacketWithCalculations($packet);

        return $packet;
    }

    public function getMonthProgress(): float
    {
        $now = now();
        return $this->calculationService->getMonthProgress($now->month, $now->year);
    }

    public function getDaysUntilFinalization(): int
    {
        return $this->calculationService->getDaysUntilMonthEnd();
    }

    public function isMonthFinalized(): bool
    {
        $now = now();
        return $this->calculationService->isMonthFinalized($now->month, $now->year);
    }

    public function getRelatedLogs()
    {
        $now = now();
        return $this->streamer->streamerLogEntries()
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->where('status', '!=', 'draft')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getPendingPackets()
    {
        return $this->streamer->profitSharePackets()
            ->whereIn('status', ['submitted', 'pending_review', 'rejected'])
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();
    }

    public function getApprovedPackets()
    {
        return $this->streamer->profitSharePackets()
            ->where('status', 'approved')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get();
    }

    public function render()
    {
        return view('livewire.streamer-profit-share-dashboard-v2', [
            'currentPacket' => $this->getCurrentMonthPacket(),
            'monthProgress' => $this->getMonthProgress(),
            'daysUntilFinalization' => $this->getDaysUntilFinalization(),
            'isFinalized' => $this->isMonthFinalized(),
            'relatedLogs' => $this->getRelatedLogs(),
            'pendingPackets' => $this->getPendingPackets(),
            'approvedPackets' => $this->getApprovedPackets(),
        ]);
    }
}
