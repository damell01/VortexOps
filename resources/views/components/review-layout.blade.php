@props(['title' => null, 'sessionId' => null, 'projectId' => null, 'breadcrumb' => null])

@php
    $projectsEnabled = \App\Support\AdminModules::isEnabled('projects');
    $reviewsEnabled = \App\Support\AdminModules::isEnabled('reviews');
    $portalTitle = $projectsEnabled ? 'VortexOps Project Hub' : 'VortexOps';
@endphp

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title . ' · ' : '' }}{{ $portalTitle }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        [x-cloak] { display: none !important; }
        :root {
            --review-bg: #08111f;
            --review-panel: rgba(10, 19, 35, 0.84);
            --review-panel-strong: rgba(8, 15, 29, 0.96);
            --review-border: rgba(148, 163, 184, 0.18);
            --review-text: #dbe6f3;
            --review-muted: #8ea3bf;
            --review-accent: #7c3aed;
            --review-accent-soft: rgba(124, 58, 237, 0.14);
            --review-cyan: #22d3ee;
        }

        body.review-shell {
            min-height: 100%;
            color: var(--review-text);
            background:
                radial-gradient(circle at top left, rgba(34, 211, 238, 0.12), transparent 26%),
                radial-gradient(circle at top right, rgba(124, 58, 237, 0.18), transparent 30%),
                linear-gradient(180deg, #08111f 0%, #091423 48%, #0c1728 100%);
            background-attachment: fixed;
        }

        .review-grid {
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: 0.32;
            background-image:
                linear-gradient(rgba(148, 163, 184, 0.08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(148, 163, 184, 0.08) 1px, transparent 1px);
            background-size: 32px 32px;
            mask-image: radial-gradient(circle at center, black 42%, transparent 100%);
        }

        .review-glow {
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(circle at 15% 20%, rgba(34, 211, 238, 0.10), transparent 22%),
                radial-gradient(circle at 78% 12%, rgba(124, 58, 237, 0.18), transparent 24%),
                radial-gradient(circle at 50% 100%, rgba(14, 165, 233, 0.10), transparent 30%);
            filter: blur(10px);
        }

        .review-topbar {
            background: rgba(8, 15, 29, 0.78);
            backdrop-filter: blur(18px);
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
            box-shadow: 0 24px 60px rgba(2, 6, 23, 0.45);
        }

        .review-brand-chip,
        .review-toolbar-chip {
            border: 1px solid rgba(148, 163, 184, 0.14);
            background: rgba(15, 23, 42, 0.62);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
        }

        .review-brand-chip:hover,
        .review-toolbar-chip:hover {
            border-color: rgba(125, 211, 252, 0.28);
            background: rgba(15, 23, 42, 0.88);
        }

        .review-main {
            position: relative;
            z-index: 1;
        }

        .review-flash {
            border: 1px solid rgba(74, 222, 128, 0.2);
            background: rgba(20, 83, 45, 0.28);
            color: #dcfce7;
            backdrop-filter: blur(12px);
        }
    </style>
    <script>window.VortexModules = {{ \Illuminate\Support\Js::from(['projects' => $projectsEnabled, 'reviews' => $reviewsEnabled]) }};</script>

    @if ($sessionId)
        <script>localStorage.setItem('vortex_review_session_id', '{{ $sessionId }}');</script>
    @endif
    @if ($projectId)
        <script>localStorage.setItem('vortex_project_id', '{{ $projectId }}');</script>
    @endif
</head>
<body class="review-shell h-full font-sans antialiased">
    <div class="review-grid"></div>
    <div class="review-glow"></div>

    <header class="review-topbar sticky top-0 z-50">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3">
            <div class="flex min-w-0 items-center gap-3">
                <a href="{{ route('review.index') }}" class="review-brand-chip flex items-center gap-3 rounded-2xl px-3 py-2 text-sm font-semibold text-slate-100 transition">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-400 via-sky-500 to-violet-600 shadow-lg shadow-violet-950/30">
                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="text-[10px] uppercase tracking-[0.22em] text-cyan-300/80">Client Workspace</div>
                        <div class="truncate text-sm text-white">Project Hub</div>
                    </div>
                </a>

                @if ($breadcrumb)
                    <div class="hidden min-w-0 items-center gap-2 text-sm text-slate-400 md:flex">
                        <span>/</span>
                        <div class="truncate">{!! $breadcrumb !!}</div>
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-2">
                @if (auth()->user()?->isSuperAdmin())
                    <a href="/admin" class="review-toolbar-chip rounded-xl px-3 py-2 text-xs font-medium text-slate-200 transition">
                        Admin Panel →
                    </a>
                @endif

                @if ($reviewsEnabled)
                    <button
                        onclick="document.getElementById('review-toggle-btn')?.click()"
                        class="rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 px-3 py-2 text-xs font-semibold text-white shadow-lg shadow-violet-950/30 transition hover:scale-[1.02]"
                    >
                        Leave Feedback
                    </button>
                @endif

                <div class="review-toolbar-chip hidden items-center gap-2 rounded-xl px-3 py-2 sm:flex">
                    <span class="h-2 w-2 rounded-full bg-emerald-400 shadow-[0_0_14px_rgba(74,222,128,0.9)]"></span>
                    <span class="max-w-[140px] truncate text-xs text-slate-300">{{ auth()->user()?->name }}</span>
                    <form method="POST" action="{{ route('filament.admin.auth.logout') }}">
                        @csrf
                        <button type="submit" class="text-xs text-slate-400 transition hover:text-white">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    @if (session('success'))
        <div class="review-main mx-auto max-w-7xl px-4 pt-4">
            <div class="review-flash rounded-2xl px-4 py-3 text-sm shadow-lg shadow-black/10">
                {{ session('success') }}
            </div>
        </div>
    @endif

    <main class="review-main mx-auto max-w-7xl px-4 py-8">
        {{ $slot }}
    </main>
</body>
</html>
