<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Resources\ShowResource;
use App\Jobs\NotifyShowReady;
use App\Jobs\ParseShowTitle;
use Filament\Resources\Pages\CreateRecord;

class CreateShow extends CreateRecord
{
    protected static string $resource = ShowResource::class;

    protected function afterCreate(): void
    {
        $show = $this->record;
        $show->update([
            'status'     => 'pending_review',
            'created_by' => auth()->id(),
        ]);

        if ($show->title) {
            ParseShowTitle::dispatch($show->id);
        }

        NotifyShowReady::dispatch($show->id);
    }
}
