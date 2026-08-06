<?php

namespace App\Livewire;

use App\Models\ProfitSharePacket;
use App\Models\Streamer;
use Livewire\Component;
use Livewire\Attributes\Validate;

class StreamerProfitShareDashboard extends Component
{
    public Streamer $streamer;
    public ?ProfitSharePacket $selectedPacket = null;
    public bool $showForm = false;
    public bool $showHistory = false;

    #[Validate('required|numeric|min:0')]
    public ?float $gross_revenue = null;

    #[Validate('required|numeric|min:0')]
    public ?float $product_cost = null;

    #[Validate('required|numeric|min:0')]
    public ?float $shipping_cost = null;

    #[Validate('nullable|numeric|min:0')]
    public ?float $other_costs = null;

    #[Validate('nullable|string|max:1000')]
    public ?string $notes = null;

    public function mount(Streamer $streamer): void
    {
        $this->streamer = $streamer->load('profitSharePackets');
    }

    public function selectPacket(ProfitSharePacket $packet): void
    {
        $this->selectedPacket = $packet;
        $this->loadPacketData();
        $this->showForm = false;
    }

    public function loadPacketData(): void
    {
        if (!$this->selectedPacket) return;

        $this->gross_revenue = $this->selectedPacket->gross_revenue;
        $this->product_cost = $this->selectedPacket->product_cost;
        $this->shipping_cost = $this->selectedPacket->shipping_cost;
        $this->other_costs = $this->selectedPacket->other_costs;
        $this->notes = $this->selectedPacket->notes;
    }

    public function editPacket(ProfitSharePacket $packet): void
    {
        if ($packet->status !== 'draft' && $packet->status !== 'rejected') {
            session()->flash('error', 'Only draft or rejected packets can be edited');
            return;
        }

        $this->selectedPacket = $packet;
        $this->loadPacketData();
        $this->showForm = true;
    }

    public function savePacket(): void
    {
        $this->validate();

        if (!$this->selectedPacket) {
            session()->flash('error', 'No packet selected');
            return;
        }

        $this->selectedPacket->update([
            'gross_revenue' => $this->gross_revenue,
            'product_cost' => $this->product_cost,
            'shipping_cost' => $this->shipping_cost,
            'other_costs' => $this->other_costs,
            'notes' => $this->notes,
        ]);

        session()->flash('success', 'Packet saved successfully');
        $this->showForm = false;
        $this->selectedPacket = null;
        $this->dispatch('refresh');
    }

    public function submitPacket(ProfitSharePacket $packet): void
    {
        if ($packet->status !== 'draft' && $packet->status !== 'rejected') {
            session()->flash('error', 'Only draft or rejected packets can be submitted');
            return;
        }

        $packet->submit();

        session()->flash('success', 'Packet submitted for review');
        $this->selectedPacket = null;
        $this->dispatch('refresh');
    }

    public function getCurrentMonthPacket(): ?ProfitSharePacket
    {
        $now = now();
        return $this->streamer->profitSharePackets()
            ->where('year', $now->year)
            ->where('month', $now->month)
            ->first();
    }

    public function getPendingPackets()
    {
        return $this->streamer->profitSharePackets()
            ->whereIn('status', ['draft', 'submitted', 'rejected'])
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
        return view('livewire.streamer-profit-share-dashboard', [
            'currentPacket' => $this->getCurrentMonthPacket(),
            'pendingPackets' => $this->getPendingPackets(),
            'approvedPackets' => $this->getApprovedPackets(),
        ]);
    }
}
