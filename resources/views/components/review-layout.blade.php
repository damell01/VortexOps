@props(['title' => null, 'sessionId' => null, 'projectId' => null, 'breadcrumb' => null])

<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title . ' · ' : '' }}VortexOps Project Hub</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>[x-cloak] { display: none !important; }</style>

    @if ($sessionId)
        <script>localStorage.setItem('vortex_review_session_id', '{{ $sessionId }}');</script>
    @endif
    @if ($projectId)
        <script>localStorage.setItem('vortex_project_id', '{{ $projectId }}');</script>
    @endif
</head>
<body class="h-full font-sans antialiased">

    <header class="sticky top-0 z-50 border-b border-gray-200 bg-white shadow-sm">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('review.index') }}" class="flex items-center gap-2 text-sm font-bold text-gray-900">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-violet-600">
                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    Project Hub
                </a>

                @if ($breadcrumb)
                    <span class="text-gray-300">/</span>
                    {!! $breadcrumb !!}
                @endif
            </div>

            <div class="flex items-center gap-3">
                @if (auth()->user()?->isSuperAdmin())
                    <a href="/admin" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                        Admin Panel →
                    </a>
                @endif

                <button
                    onclick="document.getElementById('review-toggle-btn')?.click()"
                    class="flex items-center gap-1.5 rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-violet-700"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Leave Feedback
                </button>

                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500">{{ auth()->user()?->name }}</span>
                    <form method="POST" action="{{ route('filament.admin.auth.logout') }}">
                        @csrf
                        <button type="submit" class="text-xs text-gray-400 hover:text-gray-600">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    @if (session('success'))
        <div class="mx-auto max-w-6xl px-4 pt-4">
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ session('success') }}
            </div>
        </div>
    @endif

    <main class="mx-auto max-w-6xl px-4 py-8">
        {{ $slot }}
    </main>

</body>
</html>
