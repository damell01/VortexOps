<x-filament-panels::page>
    @php($members = $this->getMemberCards())
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Team members</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $members->total() }} accounts · Search and open a card to manage access.</p>
        </div>
        <label class="w-full sm:w-72">
            <span class="sr-only">Search team members</span>
            <input wire:model.live.debounce.300ms="memberSearch" type="search" placeholder="Search name or email…"
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
        </label>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse($members as $member)
            @php
                $roles = $member->getRoleNames();
                $profile = $member->streamer;
                $initials = collect(preg_split('/\s+/', trim($member->name)))->filter()->take(2)->map(fn ($word) => strtoupper(substr($word, 0, 1)))->implode('');
            @endphp
            <article wire:key="team-member-{{ $member->id }}" class="flex min-w-0 flex-col justify-between gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-violet-300 dark:border-gray-700 dark:bg-gray-900">
                <div class="flex min-w-0 items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-sm font-bold text-violet-700 dark:bg-violet-900/40 dark:text-violet-200">{{ $initials ?: '?' }}</div>
                    <div class="min-w-0">
                        <a href="{{ \App\Filament\Resources\UserResource::getUrl('view', ['record' => $member]) }}" class="block truncate font-semibold text-gray-950 hover:text-violet-600 dark:text-white">{{ $member->name }}</a>
                        <p class="truncate text-xs text-gray-500" title="{{ $member->email }}">{{ $member->email }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @forelse($roles as $role)
                        <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-200">{{ str($role)->replace('_', ' ')->title() }}</span>
                    @empty
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">No role assigned</span>
                    @endforelse
                    @if($profile)
                        <span class="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-medium text-violet-700 dark:bg-violet-950 dark:text-violet-200">{{ str($profile->member_type ?: 'team member')->replace('_', ' ')->title() }} profile</span>
                    @endif
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-gray-100 pt-3 dark:border-gray-800">
                    <span class="text-xs text-gray-500">Joined {{ $member->created_at?->format('M j, Y') }}</span>
                    <div class="flex items-center gap-2">
                        <a href="{{ \App\Filament\Resources\UserResource::getUrl('view', ['record' => $member]) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200" aria-label="View {{ $member->name }}">View</a>
                        <a href="{{ \App\Filament\Resources\UserResource::getUrl('edit', ['record' => $member]) }}" class="rounded-lg bg-violet-600 px-3 py-2 text-xs font-semibold text-white hover:bg-violet-700" aria-label="Edit {{ $member->name }}">Edit</a>
                    </div>
                </div>
            </article>
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500">No team members match your search.</div>
        @endforelse
    </div>
    <div>{{ $members->links() }}</div>
</x-filament-panels::page>
