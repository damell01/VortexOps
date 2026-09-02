<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Resources\ShowResource;
use App\Models\Show;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShow extends EditRecord
{
    protected static string $resource = ShowResource::class;

    public function getView(): string
    {
        return 'filament.resources.show-resource.pages.edit-show';
    }

    protected function resolveRecord(int|string $key): Show
    {
        return Show::with([
            'streamers',
            'channel',
            'payouts.streamer',
        ])->findOrFail($key);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
