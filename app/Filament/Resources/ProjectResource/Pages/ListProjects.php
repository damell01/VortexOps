<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Models\Project;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    public function mount(): void
    {
        $project = Project::query()
            ->orderByDesc('is_active')
            ->orderByDesc('updated_at')
            ->first();

        if ($project) {
            $this->redirect(ProjectResource::getUrl('view', ['record' => $project]));
            return;
        }

        parent::mount();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Project Hub Setup';
    }
}
