<?php

namespace App\Livewire;

use App\Models\StreamerLogEntry;
use Livewire\Component;
use Filament\Notifications\Notification;

class EndOfStreamForm extends Component
{
    public StreamerLogEntry $log;
    public string $currentStep = 'overview';
    public bool $isSubmitting = false;

    public function mount(StreamerLogEntry $log)
    {
        $this->log = $log;
        $this->determineCurrentStep();
    }

    private function determineCurrentStep(): void
    {
        $user = auth()->user();

        if (!$this->log->isSubmitted()) {
            $this->currentStep = 'streamer_review';
        } elseif (!$this->log->reviewed_at) {
            $this->currentStep = 'admin_review';
        } elseif ($this->log->needsFulfillmentReview()) {
            $this->currentStep = 'fulfillment_review';
        } else {
            $this->currentStep = 'completed';
        }
    }

    public function submitStreamerReview(): void
    {
        $this->isSubmitting = true;

        $this->log->submitReport();

        Notification::make()
            ->title('✓ Report Submitted')
            ->body('Your streamer report has been submitted for admin review.')
            ->success()
            ->send();

        $this->dispatch('refresh');
        $this->determineCurrentStep();
        $this->isSubmitting = false;
    }

    public function approveByAdmin(): void
    {
        $user = auth()->user();
        if (!$user?->isAdmin() && !$user?->isOwner()) {
            return;
        }

        $this->isSubmitting = true;

        $this->log->update([
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'status' => 'admin_approved',
        ]);

        Notification::make()
            ->title('✓ Report Approved')
            ->body('The streamer report has been approved by admin.')
            ->success()
            ->send();

        $this->dispatch('refresh');
        $this->determineCurrentStep();
        $this->isSubmitting = false;
    }

    public function rejectByAdmin(string $reason = ''): void
    {
        $user = auth()->user();
        if (!$user?->isAdmin() && !$user?->isOwner()) {
            return;
        }

        $this->isSubmitting = true;

        $this->log->rejectByAdmin($reason);

        Notification::make()
            ->title('⚠ Report Rejected')
            ->body('The report has been sent back for corrections.')
            ->warning()
            ->send();

        $this->dispatch('refresh');
        $this->determineCurrentStep();
        $this->isSubmitting = false;
    }

    public function approveFulfillment(): void
    {
        $user = auth()->user();
        if (!$user?->isFulfillmentAdmin() && !$user?->isOwner()) {
            return;
        }

        $this->isSubmitting = true;

        $this->log->update([
            'fulfillment_reviewed_by' => $user->id,
            'fulfillment_reviewed_at' => now(),
        ]);

        Notification::make()
            ->title('✓ Fulfillment Approved')
            ->body('Fulfillment review complete.')
            ->success()
            ->send();

        $this->dispatch('refresh');
        $this->determineCurrentStep();
        $this->isSubmitting = false;
    }

    public function render()
    {
        return view('livewire.end-of-stream-form', [
            'currentStep' => $this->currentStep,
            'canSubmit' => $this->log->canStreamerEdit() && !$this->log->isSubmitted(),
            'canApprove' => auth()->user()?->isAdmin() || auth()->user()?->isOwner(),
            'canFulfillment' => auth()->user()?->isFulfillmentAdmin() || auth()->user()?->isOwner(),
            'needsFulfillment' => $this->log->needsFulfillmentReview(),
        ]);
    }
}
