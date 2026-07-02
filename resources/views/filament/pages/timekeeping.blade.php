<x-filament-panels::page>
    <div class="max-w-3xl mx-auto py-8">

        {{-- Hero --}}
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-violet-100 dark:bg-violet-900/40 mb-4">
                <x-heroicon-o-clock class="h-8 w-8 text-violet-600 dark:text-violet-400" />
            </div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Timekeeping</h1>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto">
                Employee time tracking, shift logs, and labor cost reporting for Vortex Breaks operations.
            </p>
            <div class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-amber-100 dark:bg-amber-900/30 px-3 py-1 text-xs font-medium text-amber-700 dark:text-amber-400">
                <x-heroicon-o-wrench-screwdriver class="h-3.5 w-3.5" />
                Coming soon — this module is under development
            </div>
        </div>

        {{-- Planned features grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">

            @php
            $features = [
                [
                    'icon'  => 'heroicon-o-user-group',
                    'color' => 'blue',
                    'title' => 'Employee Shifts',
                    'desc'  => 'Log clock-in / clock-out times per employee, link shifts to shows, and track daily hours with notes.',
                ],
                [
                    'icon'  => 'heroicon-o-currency-dollar',
                    'color' => 'emerald',
                    'title' => 'Labor Cost Reporting',
                    'desc'  => 'See total labor cost per show or per week, broken out by role and compared against gross revenue.',
                ],
                [
                    'icon'  => 'heroicon-o-calendar-days',
                    'color' => 'violet',
                    'title' => 'Schedule View',
                    'desc'  => 'Weekly schedule grid with shift assignments, open slots, and coverage warnings.',
                ],
                [
                    'icon'  => 'heroicon-o-clipboard-document-list',
                    'color' => 'amber',
                    'title' => 'Timesheets',
                    'desc'  => 'Exportable timesheets per pay period with approval workflow and payroll-ready summaries.',
                ],
                [
                    'icon'  => 'heroicon-o-chart-bar',
                    'color' => 'rose',
                    'title' => 'Hours Analytics',
                    'desc'  => 'Breakdown of hours by team member, show type, and time period — spot overtime and optimize staffing.',
                ],
                [
                    'icon'  => 'heroicon-o-bolt',
                    'color' => 'indigo',
                    'title' => 'Show Integration',
                    'desc'  => 'Shifts linked to Show records so labor cost rolls up into per-show P&L automatically.',
                ],
            ];

            $colorMap = [
                'blue'    => 'bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400',
                'emerald' => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400',
                'violet'  => 'bg-violet-100 dark:bg-violet-900/40 text-violet-600 dark:text-violet-400',
                'amber'   => 'bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400',
                'rose'    => 'bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400',
                'indigo'  => 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400',
            ];
            @endphp

            @foreach ($features as $feature)
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 rounded-lg p-2 {{ $colorMap[$feature['color']] }}">
                            <x-dynamic-component :component="$feature['icon']" class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $feature['title'] }}</h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 leading-relaxed">{{ $feature['desc'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Enable/disable note --}}
        <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50 px-6 py-4 text-center">
            <p class="text-xs text-gray-500 dark:text-gray-400">
                This module is togglable in
                <a href="{{ \App\Filament\Pages\AppSettings::getUrl() }}" class="font-medium text-violet-600 dark:text-violet-400 hover:underline">App Settings → Workspace Modules</a>.
                Disabling it hides it from all users until you re-enable it.
            </p>
        </div>

    </div>
</x-filament-panels::page>
