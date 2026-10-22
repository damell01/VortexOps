<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Spatie\Permission\Models\Role;
use Livewire\WithPagination;

class ListUsers extends ListRecords
{
    use \App\Filament\Concerns\DownloadsTableCsv;
    use WithPagination;

    public string $memberSearch = '';

    public function updatedMemberSearch(): void
    {
        $this->resetPage();
    }

    public function getMemberCards(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return UserResource::getEloquentQuery()
            ->when(trim($this->memberSearch) !== '', function ($query): void {
                $term = '%'.trim($this->memberSearch).'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->orderBy('name')->paginate(12);
    }

    protected static string $resource = UserResource::class;

    protected string $view = 'filament.resources.user-resource.pages.list-users';

    public function roleMatrix(): array
    {
        return Role::query()->with('permissions')->orderBy('name')->get()->map(fn ($role) => [
            'name' => str($role->name)->replace('_', ' ')->title()->toString(),
            'permissions' => $role->permissions->pluck('name')->take(6)->values()->all(),
            'count' => $role->permissions->count(),
        ])->all();
    }

    public function getSubheading(): ?string
    {
        return 'Manage team access, roles, and linked streamer profiles.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add User')->icon('heroicon-o-plus')->color('primary')];
    }
}
