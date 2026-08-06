<?php

namespace App\Livewire;

use App\Models\Show;
use App\Models\Streamer;
use Livewire\Component;
use Livewire\Attributes\Validate;
use Carbon\Carbon;

class CreateManualShow extends Component
{
    public bool $showModal = false;
    public Streamer $streamer;

    #[Validate('required|string|max:255')]
    public string $title = '';

    #[Validate('required|string|max:100')]
    public string $channel = '';

    #[Validate('required|date_format:Y-m-d\TH:i')]
    public string $showDatetime = '';

    #[Validate('required|numeric|min:0')]
    public float $grossRevenue = 0;

    public function mount(Streamer $streamer): void
    {
        $this->streamer = $streamer;
        $this->showDatetime = now()->format('Y-m-d\TH:i');
    }

    public function openModal(): void
    {
        $this->reset('title', 'channel', 'grossRevenue');
        $this->showDatetime = now()->format('Y-m-d\TH:i');
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function createShow(): void
    {
        $validated = $this->validate();

        $show = Show::create([
            'streamer_id' => $this->streamer->id,
            'title' => $validated['title'],
            'channel' => $validated['channel'],
            'show_date' => Carbon::createFromFormat('Y-m-d\TH:i', $validated['showDatetime']),
            'gross_revenue' => $validated['grossRevenue'],
        ]);

        session()->flash('success', "✓ Show '{$show->title}' created successfully!");
        $this->closeModal();

        // If show is in the past or current (not future), redirect to show detail to fill in log
        if ($show->show_date <= now()) {
            $this->redirect(route('filament.admin.resources.shows.edit', $show), navigate: true);
        } else {
            $this->dispatch('refresh');
        }
    }

    public function render()
    {
        return view('livewire.create-manual-show');
    }
}
