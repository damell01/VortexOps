<?php

namespace App\Livewire;

use App\Models\Show;
use App\Models\Streamer;
use Carbon\Carbon;
use Livewire\Component;

class CreateManualShow extends Component
{
    public Streamer $streamer;
    public bool $showModal = false;
    public string $title = '';
    public string $showDatetime = '';
    public string $grossRevenue = '';
    public string $notes = '';

    protected $rules = [
        'title' => 'required|string|max:255',
        'showDatetime' => 'required|date_format:Y-m-d\TH:i',
        'grossRevenue' => 'required|numeric|min:0',
        'notes' => 'nullable|string|max:1000',
    ];

    public function mount(Streamer $streamer): void
    {
        $this->streamer = $streamer;
        $this->showDatetime = now()->format('Y-m-d\TH:i');
    }

    public function openModal(): void
    {
        $this->showModal = true;
        $this->reset(['title', 'grossRevenue', 'notes']);
        $this->showDatetime = now()->format('Y-m-d\TH:i');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset();
    }

    public function createShow(): void
    {
        $this->validate();

        $showDate = Carbon::createFromFormat('Y-m-d\TH:i', $this->showDatetime);

        $show = Show::create([
            'title' => $this->title,
            'show_date' => $showDate,
            'gross_revenue' => (float) $this->grossRevenue,
            'notes' => $this->notes ?: null,
            'status' => 'completed',
            'import_source' => 'manual',
        ]);

        // Attach streamer to show
        $show->streamers()->attach($this->streamer->id);

        $this->closeModal();

        // If show is in past or current, redirect to end-of-stream log form
        if ($showDate <= now()) {
            redirect()->route('filament.admin.resources.shows.edit', $show);
        } else {
            // Future show, just refresh and show success message
            $this->dispatch('showCreated');
            $this->dispatch('notify', message: 'Show created successfully!');
        }
    }

    public function render()
    {
        return view('livewire.create-manual-show');
    }
}
