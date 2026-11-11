<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\WhatnotChannel;
use App\Services\GiveawayRoi;
use App\Support\AdminModules;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Giveaway ROI — what giveaways cost per show (product given away at cost
 * plus Whatnot's giveaway spend) against the net and new buyers the show
 * brought, and shows with giveaways beside shows without. Admin only.
 */
class GiveawayRoiReport extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'reporting';
    protected static ?string $title = 'Giveaway ROI';
    protected static ?string $slug = 'giveaway-roi';
    protected static bool $shouldRegisterNavigation = false;

    public const RANGES = ['30' => 'Last 30 days', '90' => 'Last 90 days', '365' => 'Last 12 months'];

    #[Url]
    public string $range = '30';

    #[Url]
    public string $channel = '';

    protected static function passesModuleAccessCheck(): bool
    {
        return (bool) (auth()->user()?->isAdmin() || auth()->user()?->isOwner());
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('reporting');
    }

    public function getView(): string { return 'filament.pages.giveaway-roi'; }

    private function report(): array
    {
        $days = (int) (array_key_exists($this->range, self::RANGES) ? $this->range : '30');

        return app(GiveawayRoi::class)->between(now()->subDays($days - 1)->toDateString(), now()->toDateString(), $this->channel !== '' ? (int) $this->channel : null);
    }

    protected function getViewData(): array
    {
        return $this->report() + [
            'channels' => WhatnotChannel::query()->orderBy('name')->pluck('name', 'id'),
            'ranges' => self::RANGES,
        ];
    }

    public function export(): StreamedResponse
    {
        $shows = $this->report()['shows'];

        return response()->streamDownload(function () use ($shows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Show', 'Channel', 'Giveaways', 'Product cost', 'Whatnot giveaway spend', 'Total giveaway cost', 'Gross', 'Whatnot net', 'Buyers', 'New buyers', 'Cost per new buyer', 'Net per $1 of giveaways']);
            foreach ($shows as $s) {
                fputcsv($out, [$s['date'], $s['title'], $s['channel'], $s['giveaways'], $s['product_cost'], $s['whatnot_spend'], $s['cost'], $s['gross'], $s['net'], $s['buyers'], $s['new_buyers'], $s['cost_per_new_buyer'], $s['net_per_dollar']]);
            }
            fclose($out);
        }, 'giveaway-roi.csv', ['Content-Type' => 'text/csv']);
    }
}
