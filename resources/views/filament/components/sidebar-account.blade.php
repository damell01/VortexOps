@php
    /** @var \App\Models\User|null $user */
    $user = auth()->user();

    $name = trim((string) ($user?->name ?? ''));
    $name = $name !== '' ? $name : (string) ($user?->email ?? 'Account');

    $role = match (true) {
        (bool) ($user?->isOwner() ?? false) => 'Owner',
        (bool) ($user?->isAdmin() ?? false) => 'Admin',
        (bool) ($user?->isStreamer() ?? false) => 'Streamer',
        default => 'Member',
    };

    // Initials are rendered as text rather than an <img>; the default avatar
    // service (ui-avatars.com) is not reachable from this deployment and was
    // painting a broken-image box.
    $initials = collect(preg_split('/\s+/', $name))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
    $initials = $initials !== '' ? $initials : 'VB';
@endphp

<div class="vx-account">
    <div class="vx-account-row">
        <span class="vx-account-avatar" aria-hidden="true">{{ $initials }}</span>

        <span class="vx-account-meta">
            <span class="vx-account-name">{{ $name }}</span>
            <span class="vx-account-role">{{ $role }}</span>
        </span>
    </div>

    <form method="POST" action="{{ filament()->getLogoutUrl() }}" class="vx-account-logout-form">
        @csrf
        <button type="submit" class="vx-account-logout">
            <x-filament::icon icon="heroicon-m-arrow-right-on-rectangle" class="vx-account-logout-icon" />
            <span>Log out</span>
        </button>
    </form>
</div>
