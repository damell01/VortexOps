<?php

namespace App\Livewire;

use App\Models\FeedbackTicket;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class FeedbackWidget extends Component
{
    use WithFileUploads;

    public string  $title       = '';
    public string  $description = '';
    public string  $priority    = 'medium';
    public bool    $submitted   = false;
    /** @var mixed */
    public $screenshot = null;

    protected array $rules = [
        'title'       => 'required|string|max:255',
        'description' => 'nullable|string|max:5000',
        'priority'    => 'in:low,medium,high',
        'screenshot'  => 'nullable|image|max:4096',
    ];

    public function submit(): void
    {
        $this->validate();

        $screenshotPath = null;
        if ($this->screenshot) {
            $screenshotPath = $this->screenshot->store('feedback', 'public');
        }

        $user = Auth::user();

        FeedbackTicket::create([
            'title'           => $this->title,
            'description'     => $this->description ?: null,
            'screenshot_path' => $screenshotPath,
            'page_url'        => url()->current(),
            'priority'        => $this->priority,
            'submitted_by'    => $user?->id,
            'submitted_name'  => $user?->name,
            'submitted_email' => $user?->email,
        ]);

        $this->reset(['title', 'description', 'priority', 'screenshot']);
        $this->submitted = true;
    }

    public function resetWidget(): void
    {
        $this->reset();
        $this->submitted = false;
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.feedback-widget');
    }
}
