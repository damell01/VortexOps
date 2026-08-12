<?php

namespace App\Livewire;

use App\Models\Show;
use App\Models\Streamer;
use App\Models\WhatnotChannel;
use Carbon\Carbon;
use Livewire\Component;

class CreateManualShow extends Component
{
    public Streamer $streamer;
    public bool $showModal = false;
    public string $title = '';
    public string $showDatetime = '';
    public string $grossRevenue = '';
    public ?string $whatnotChannelId = null;
    public array $streamerIds = [];
    public ?string $showDuration = null;
    public ?string $startTime = null;
    public ?string $endTime = null;

    protected $rules = [
        'title' => 'required|string|max:255',
        'showDatetime' => 'required|date_format:Y-m-d\TH:i',
        'grossRevenue' => 'nullable|numeric|min:0',
        'whatnotChannelId' => 'nullable|exists:whatnot_channels,id',
        'streamerIds' => 'required|array|min:1',
        'streamerIds.*' => 'exists:streamers,id',
        'showDuration' => 'nullable|numeric|min:0',
        'startTime' => 'nullable|date_format:H:i',
        'endTime' => 'nullable|date_format:H:i',
    ];

    public function mount(Streamer $streamer): void
    {
        $this->streamer = $streamer;
        $this->showDatetime = now()->format('Y-m-d\TH:i');
        $this->streamerIds = $streamer ? [$streamer->id] : [];
    }

    public function openModal(): void
    {
        $this->showModal = true;
        $this->reset(['title', 'grossRevenue', 'whatnotChannelId', 'showDuration', 'startTime', 'endTime']);
        $this->showDatetime = now()->format('Y-m-d\TH:i');
        $this->streamerIds = $this->streamer ? [$this->streamer->id] : [];
    }

    #[\Livewire\Attributes\On('updated_startTime')]
    #[\Livewire\Attributes\On('updated_endTime')]
    public function calculateDuration(): void
    {
        if (!empty($this->startTime) && !empty($this->endTime)) {
            $start = \DateTime::createFromFormat('H:i', $this->startTime);
            $end = \DateTime::createFromFormat('H:i', $this->endTime);

            if ($start && $end) {
                $interval = $start->diff($end);
                $minutes = ($interval->h * 60) + $interval->i;

                if ($minutes > 0) {
                    $this->showDuration = (string) $minutes;
                }
            }
        }
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
            'gross_revenue' => $this->grossRevenue ? (float) $this->grossRevenue : 0,
            'whatnot_channel_id' => $this->whatnotChannelId ? (int) $this->whatnotChannelId : null,
            'show_duration' => $this->showDuration ? (int) $this->showDuration : null,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'status' => 'completed',
            'import_source' => 'manual',
        ]);

        // Attach streamers to show
        if (!empty($this->streamerIds)) {
            $show->streamers()->attach($this->streamerIds);
        }

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
        return view('livewire.create-manual-show', [
            'streamers' => Streamer::where('status', 'active')->orderBy('name')->get(),
            'channels' => WhatnotChannel::where('status', 'active')->orderBy('name')->get(),
        ]);
    }
}
