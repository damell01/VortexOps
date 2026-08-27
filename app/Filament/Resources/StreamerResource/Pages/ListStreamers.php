<?php

namespace App\Filament\Resources\StreamerResource\Pages;

use App\Filament\Resources\StreamerResource;
use App\Models\Streamer;
use Filament\Actions\CreateAction;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;

class ListStreamers extends ListRecords
{
    protected static string $resource = StreamerResource::class;

    public function getSubheading(): ?string
    {
        return 'Everyone you pay — streamers and fulfillment staff. Payout type and rate, channel routing rules, and the login each one is linked to.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    /**
     * Streamers and fulfillment staff on one page.
     *
     * They are the same kind of record: the pay terms, the rate columns and the
     * payout pipeline are identical, and only the work differs. Splitting them
     * into two resources would have meant maintaining two copies of the payout
     * form, which is how two ways of computing the same pay end up disagreeing.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Everyone')
                ->badge(fn () => Streamer::query()->count()),

            'streamers' => Tab::make('Streamers')
                ->modifyQueryUsing(fn ($query) => $query->streamers())
                ->badge(fn () => Streamer::query()->streamers()->count()),

            'fulfillment' => Tab::make('Fulfillment')
                ->modifyQueryUsing(fn ($query) => $query->fulfillment())
                ->badge(fn () => Streamer::query()->fulfillment()->count()),
        ];
    }
}
