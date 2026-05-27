@props(['title' => null, 'sessionId' => null, 'projectId' => null, 'breadcrumb' => null])

@php
    $projectsEnabled = \App\Support\AdminModules::isEnabled('projects');
    $reviewsEnabled = \App\Support\AdminModules::isEnabled('reviews');
    $portalTitle = $projectsEnabled ? 'VortexOps Project Hub' : 'VortexOps';
    $showReviewButton = \App\Models\Setting::getBool('show_review_button', true);
@endphp

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title . ' - ' : '' }}{{ $portalTitle }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        [x-cloak] { display: none !important; }

        body.review-shell {
            min-height: 100%;
            color: #0f172a;
            background:
                radial-gradient(circle at top right, rgba(34, 211, 238, 0.12), transparent 18rem),
                radial-gradient(circle at top left, rgba(109, 40, 217, 0.08), transparent 24rem),
                linear-gradient(180deg, #f8fbff 0%, #eef3fb 52%, #e8eef8 100%);
            background-attachment: fixed;
        }

        .review-grid {
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: 0.18;
            background-image:
                linear-gradient(rgba(148, 163, 184, 0.08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(148, 163, 184, 0.08) 1px, transparent 1px);
            background-size: 32px 32px;
            mask-image: radial-gradient(circle at center, black 46%, transparent 100%);
        }

        .review-glow {
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(circle at 12% 16%, rgba(34, 211, 238, 0.16), transparent 20%),
                radial-gradient(circle at 80% 10%, rgba(124, 58, 237, 0.11), transparent 22%),
                radial-gradient(circle at 50% 100%, rgba(14, 165, 233, 0.10), transparent 26%);
            filter: blur(14px);
        }

        .review-topbar {
            background: rgba(255, 255, 255, 0.78);
            backdrop-filter: blur(16px) saturate(180%);
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
        }

        .review-brand-chip,
        .review-toolbar-chip {
            border: 1px solid rgba(15, 23, 42, 0.08);
            background: rgba(255, 255, 255, 0.88);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05);
        }

        .review-brand-chip:hover,
        .review-toolbar-chip:hover {
            border-color: rgba(34, 211, 238, 0.28);
            background: rgba(255, 255, 255, 0.96);
        }

        .review-main {
            position: relative;
            z-index: 1;
        }

        .review-flash {
            border: 1px solid rgba(34, 197, 94, 0.18);
            background: rgba(220, 252, 231, 0.85);
            color: #166534;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.05);
        }

        .review-surface {
            border: 1px solid rgba(148, 163, 184, 0.20);
            background: rgba(255, 255, 255, 0.90);
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.06);
            backdrop-filter: blur(18px);
        }

        .review-hero {
            border: 1px solid rgba(34, 211, 238, 0.16);
            background:
                radial-gradient(circle at top right, rgba(34, 211, 238, 0.14), transparent 26%),
                radial-gradient(circle at 20% 0%, rgba(109, 40, 217, 0.08), transparent 24%),
                rgba(255, 255, 255, 0.92);
            box-shadow: 0 24px 56px rgba(15, 23, 42, 0.08);
        }

        .review-muted-card {
            background: rgba(248, 250, 252, 0.82);
            border: 1px solid rgba(226, 232, 240, 0.95);
        }

        .review-kicker {
            color: #0f766e;
        }
    </style>
    <script>
        window.VortexModules = {{ \Illuminate\Support\Js::from([
            'projects' => $projectsEnabled,
            'reviews' => $reviewsEnabled,
            'showReviewButton' => $showReviewButton,
        ]) }};
    </script>

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
        <div class="mx-auto flex max-w-[1600px] items-center justify-between gap-4 px-4 py-3">
            <div class="flex min-w-0 items-center gap-3">
                <a href="{{ route('review.index') }}" class="review-brand-chip flex items-center gap-3 rounded-2xl px-3 py-2 text-sm font-semibold text-slate-900 transition">
                    <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl ring-1 ring-cyan-300/40">
                        @if (file_exists(public_path(\App\Support\Branding::DEFAULT_LOGO_ASSET)))
                            <img src="{{ asset(\App\Support\Branding::DEFAULT_LOGO_ASSET) }}" alt="VortexOps" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-cyan-400 via-sky-500 to-violet-600 text-white shadow-lg shadow-cyan-500/20">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <div class="review-kicker text-[10px] uppercase tracking-[0.22em]">Client Workspace</div>
                        <div class="truncate text-sm text-slate-900">Project Hub</div>
                    </div>
                </a>

                @if ($breadcrumb)
                    <div class="hidden min-w-0 items-center gap-2 text-sm text-slate-500 lg:flex">
                        <span>/</span>
                        <div class="truncate">{!! $breadcrumb !!}</div>
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-2">
                @if (auth()->user()?->isSuperAdmin())
                    <a href="/admin" class="review-toolbar-chip rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 transition">
                        Admin Panel &rarr;
                    </a>
                @endif

                @if ($reviewsEnabled && $showReviewButton)
                    <button
                        onclick="document.getElementById('review-toggle-btn')?.click()"
                        class="rounded-xl bg-gradient-to-r from-cyan-500 via-sky-500 to-violet-600 px-3 py-2 text-xs font-semibold text-white shadow-lg shadow-cyan-500/20 transition hover:scale-[1.02]"
                    >
                        Leave Feedback
                    </button>
                @endif

                <div class="review-toolbar-chip hidden items-center gap-2 rounded-xl px-3 py-2 sm:flex">
                    <span class="h-2 w-2 rounded-full bg-emerald-400 shadow-[0_0_14px_rgba(74,222,128,0.9)]"></span>
                    <span class="max-w-[140px] truncate text-xs text-slate-600">{{ auth()->user()?->name }}</span>
                    <form method="POST" action="{{ route('filament.admin.auth.logout') }}">
                        @csrf
                        <button type="submit" class="text-xs text-slate-500 transition hover:text-slate-900">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    @if (session('success'))
        <div class="review-main mx-auto max-w-[1600px] px-4 pt-4">
            <div class="review-flash rounded-2xl px-4 py-3 text-sm">
                {{ session('success') }}
            </div>
        </div>
    @endif

    <main class="review-main mx-auto max-w-[1600px] px-4 py-8">
        {{ $slot }}
    </main>
</body>
</html>
