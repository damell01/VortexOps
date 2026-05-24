<?php

namespace App\Livewire;

use App\Models\FeedbackTicket;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class FeedbackWidget extends Component
{
    public string $title       = '';
    public string $description = '';
    public string $priority    = 'medium';
    public bool   $submitted   = false;

    protected array $rules = [
        'title'       => 'required|string|max:255',
        'description' => 'nullable|string|max:5000',
        'priority'    => 'in:low,medium,high',
    ];

    public function submitWithScreenshot(string $screenshot, string $pageUrl): void
    {
        $this->validate();

        $screenshotPath = null;
        if ($screenshot && str_starts_with($screenshot, 'data:image/')) {
            $data      = substr($screenshot, strpos($screenshot, ',') + 1);
            $imageData = base64_decode($data);
            $filename  = 'feedback/screenshot_' . uniqid('', true) . '.png';
            Storage::disk('public')->makeDirectory('feedback');
            Storage::disk('public')->put($filename, $imageData);
            $screenshotPath = $filename;
        }

        $user = Auth::user();

        FeedbackTicket::create([
            'title'           => $this->title,
            'description'     => $this->description ?: null,
            'screenshot_path' => $screenshotPath,
            'page_url'        => $pageUrl ?: null,
            'priority'        => $this->priority,
            'submitted_by'    => $user?->id,
            'submitted_name'  => $user?->name,
            'submitted_email' => $user?->email,
        ]);

        $this->reset(['title', 'description', 'priority']);
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
