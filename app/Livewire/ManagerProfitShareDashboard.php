<?php

namespace App\Livewire;

use App\Models\ProfitSharePacket;
use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\Validate;

class ManagerProfitShareDashboard extends Component
{
    public User $manager;
    public ?ProfitSharePacket $selectedPacket = null;
    public string $filterStatus = 'submitted';

    #[Validate('nullable|string|max:2000')]
    public ?string $approvalNotes = null;

    #[Validate('required|string|min:10|max:2000')]
    public ?string $rejectionReason = null;

    public function mount(User $manager): void
    {
        if (!$manager->isAdmin() && !$manager->managedStreamers()->exists()) {
            abort(403, 'You are not authorized to access this dashboard');
        }

        $this->manager = $manager->load('managedStreamers');
    }

    public function selectPacket(ProfitSharePacket $packet): void
    {
        if ($packet->manager_id !== $this->manager->id && !$this->manager->isAdmin()) {
            abort(403, 'You are not authorized to review this packet');
        }

        $this->selectedPacket = $packet->load('streamer');
    }

    public function approvePacket(): void
    {
        if (!$this->selectedPacket) {
            session()->flash('error', 'No packet selected');
            return;
        }

        if ($this->selectedPacket->status !== 'submitted') {
            session()->flash('error', 'Only submitted packets can be approved');
            return;
        }

        $this->selectedPacket->approve();

        session()->flash('success', "Packet approved for {$this->selectedPacket->streamer->name}");
        $this->selectedPacket = null;
        $this->dispatch('refresh');
    }

    public function rejectPacket(): void
    {
        $this->validate([
            'rejectionReason' => 'required|string|min:10|max:2000',
        ]);

        if (!$this->selectedPacket) {
            session()->flash('error', 'No packet selected');
            return;
        }

        if ($this->selectedPacket->status !== 'submitted') {
            session()->flash('error', 'Only submitted packets can be rejected');
            return;
        }

        $this->selectedPacket->reject($this->rejectionReason);

        session()->flash('success', "Packet rejected for {$this->selectedPacket->streamer->name}. Streamer will be notified.");
        $this->selectedPacket = null;
        $this->rejectionReason = null;
        $this->dispatch('refresh');
    }

    public function getPackets()
    {
        $query = ProfitSharePacket::query();

        // If not admin, only show own managed streamers
        if (!$this->manager->isAdmin()) {
            $query->whereIn('streamer_id', $this->manager->managedStreamers()->pluck('streamers.id'));
        }

        // Filter by status
        $query->where('status', $this->filterStatus);

        return $query->with('streamer')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getStatsForManager()
    {
        $query = ProfitSharePacket::query();

        if (!$this->manager->isAdmin()) {
            $query->whereIn('streamer_id', $this->manager->managedStreamers()->pluck('streamers.id'));
        }

        return [
            'pending' => (clone $query)->where('status', 'submitted')->count(),
            'approved' => (clone $query)->where('status', 'approved')->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
        ];
    }

    public function render()
    {
        return view('livewire.manager-profit-share-dashboard', [
            'packets' => $this->getPackets(),
            'stats' => $this->getStatsForManager(),
        ]);
    }
}
