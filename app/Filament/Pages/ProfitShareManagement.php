<?php

namespace App\Filament\Pages;

use App\Models\Payout;
use App\Models\Streamer;
use App\Support\AdminModules;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProfitShareManagement extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static string $moduleSlug = 'payouts';
    protected static ?string $title = 'Profit Share Distribution';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-share-2';

    public ?Streamer $streamer = null;
    public ?array $distributionData = [];
    public string $selectedShowId = '';
    public float $profitShareAmount = 0;
    public float $managerPercentage = 0;
    public string $managerNotes = '';

    public function getView(): string
    {
        return 'filament.pages.profit-share-management';
    }

    public static function getNavigationGroup(): string | null
    {
        return 'Payouts';
    }

    public static function getNavigationSort(): ?int
    {
        return 45;
    }

    public function getSubheading(): ?string
    {
        return 'Manage profit share distribution between streamer and manager.';
    }

    public function getStreamerProperty()
    {
        $user = auth()->user();
        if ($user?->isStreamer() && !$user->isAdmin()) {
            return $user->streamer;
        }
        return null;
    }

    public function getApprovedPayoutsProperty()
    {
        $query = Payout::where('status', 'approved');

        if ($this->streamer) {
            $query->where('streamer_id', $this->streamer->id);
        }

        return $query
            ->with(['show', 'streamer'])
            ->where('calculated_payout', '>', 0)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function submitDistribution(): void
    {
        if (!$this->selectedShowId) {
            Notification::make()
                ->title('Please select a payout')
                ->danger()
                ->send();
            return;
        }

        if ($this->managerPercentage < 0 || $this->managerPercentage > 100) {
            Notification::make()
                ->title('Manager percentage must be between 0 and 100')
                ->danger()
                ->send();
            return;
        }

        try {
            $payout = Payout::findOrFail($this->selectedShowId);

            $streamerAmount = $this->profitShareAmount * (1 - ($this->managerPercentage / 100));
            $managerAmount = $this->profitShareAmount * ($this->managerPercentage / 100);

            // Store distribution info (you may want to create a distribution table)
            $payout->update([
                'manager_share_percentage' => $this->managerPercentage,
                'manager_share_amount' => $managerAmount,
                'streamer_share_amount' => $streamerAmount,
                'distribution_notes' => $this->managerNotes,
                'distribution_status' => 'pending_approval',
            ]);

            Notification::make()
                ->title('✓ Distribution saved')
                ->body("Profit share split: Streamer \${$streamerAmount}, Manager \${$managerAmount}")
                ->success()
                ->send();

            $this->selectedShowId = '';
            $this->profitShareAmount = 0;
            $this->managerPercentage = 0;
            $this->managerNotes = '';
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error saving distribution')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return AdminModules::isEnabled('payouts') && ($user?->isAdmin() || $user?->isStreamer());
    }
}
