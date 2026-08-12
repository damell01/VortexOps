<x-filament-panels::page>
    @php
        $steps = [
            [
                'icon' => 'heroicon-o-arrow-down-tray', 'color' => 'sky',
                'title' => '1. Shows import automatically',
                'body' => 'A scheduled job pulls finished shows from Whatnot every few minutes — along with the items that sold in each one. New shows land in <strong>Pending Review</strong>, and each show gets a <strong>Streamer Log</strong> entry created for it.',
            ],
            [
                'icon' => 'heroicon-o-clipboard-document-list', 'color' => 'amber',
                'title' => '2. The streamer enriches their log',
                'body' => 'From <strong>Streamer Log → To Review</strong>, a streamer opens their show and, right on that page, maps each sold item to inventory and confirms its cost (<strong>Fill Costs</strong> does the mapped ones in one click). They add hours and product cost, then mark it <strong>Streamer Reviewed</strong>. Streamers only see shows they were on.',
            ],
            [
                'icon' => 'heroicon-o-check-badge', 'color' => 'emerald',
                'title' => '3. Admin reviews & approves',
                'body' => 'An admin opens the reviewed entry, checks the numbers and pay breakdown, and <strong>Approves</strong> it. If something needs changing after approval, <strong>Send Back</strong> reopens it for the streamer.',
            ],
            [
                'icon' => 'heroicon-o-view-columns', 'color' => 'violet',
                'title' => '4. Track the pipeline',
                'body' => 'The <strong>Status Board</strong> shows every show moving through the pipeline (Pending Review → Mapping → Pending Approval → Reconciled), with a time-in-status badge so nothing gets stuck.',
            ],
            [
                'icon' => 'heroicon-o-banknotes', 'color' => 'emerald',
                'title' => '5. Run payouts',
                'body' => 'A weekly <strong>Pay Run</strong> groups each streamer\'s payouts. <strong>Preview</strong> shows exactly what everyone will receive (after loan repayments) before you finalize, then export for payment.',
            ],
        ];

        $modules = [
            ['icon' => 'heroicon-o-video-camera', 'title' => 'Shows', 'body' => 'Every show carries its financials, a <strong>Net Margin</strong> column, an <strong>Engagement</strong> summary (viewers, conversion, ratings), and a P&L breakdown. Filter tabs focus on what needs review.'],
            ['icon' => 'heroicon-o-presentation-chart-line', 'title' => 'Product Insights', 'body' => 'Which products earn the most margin, how fast they sell (<strong>sell-through</strong>), and what\'s <strong>dead stock</strong> — capital sitting unsold. All from your sales + inventory data.'],
            ['icon' => 'heroicon-o-cube', 'title' => 'Inventory & Receiving', 'body' => 'Receive pallets, map lines to products, and receive by barcode. Costs (WAC) update on every receipt and flow into each show\'s COGS.'],
            ['icon' => 'heroicon-o-shield-check', 'title' => 'Roles & Access', 'body' => 'Only the owner grants admin/super-admin. Per-role page visibility controls what each role sees in the sidebar. Streamers are scoped to their own data everywhere.'],
        ];

        $tone = fn (string $c) => [
            'sky' => ['bg' => 'bg-sky-100 dark:bg-sky-500/15', 'text' => 'text-sky-600 dark:text-sky-400'],
            'amber' => ['bg' => 'bg-amber-100 dark:bg-amber-500/15', 'text' => 'text-amber-600 dark:text-amber-400'],
            'emerald' => ['bg' => 'bg-emerald-100 dark:bg-emerald-500/15', 'text' => 'text-emerald-600 dark:text-emerald-400'],
            'violet' => ['bg' => 'bg-violet-100 dark:bg-violet-500/15', 'text' => 'text-violet-600 dark:text-violet-400'],
        ][$c] ?? ['bg' => 'bg-gray-100 dark:bg-white/10', 'text' => 'text-gray-500'];
    @endphp

    <div class="space-y-8">
        {{-- What do I do? (role-specific) --}}
        <section>
            <div class="flex items-center gap-2 mb-4">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">What do I do?</h2>
                <span class="inline-flex items-center rounded-full bg-primary-100 dark:bg-primary-500/15 px-2.5 py-0.5 text-xs font-medium text-primary-600 dark:text-primary-400">
                    You're: {{ $this->myRoleGuide['label'] }}
                </span>
            </div>
            <div class="space-y-3">
                @foreach ($this->myRoleGuide['items'] as $item)
                    <div class="flex gap-4 rounded-xl border border-primary-200 dark:border-primary-500/20 bg-primary-50/50 dark:bg-primary-500/5 p-4">
                        <div class="shrink-0 h-8 w-8 rounded-lg bg-primary-100 dark:bg-primary-500/15 flex items-center justify-center">
                            <x-heroicon-o-check class="h-4 w-4 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $item['title'] }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-300 mt-0.5 leading-relaxed">{!! $item['body'] !!}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- The core loop --}}
        <section>
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">The show-to-payout loop</h2>
            <div class="space-y-3">
                @foreach ($steps as $s)
                    @php $t = $tone($s['color']); @endphp
                    <div class="flex gap-4 rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-4">
                        <div class="shrink-0 h-10 w-10 rounded-lg {{ $t['bg'] }} flex items-center justify-center">
                            <x-dynamic-component :component="$s['icon']" class="h-5 w-5 {{ $t['text'] }}" />
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $s['title'] }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-300 mt-0.5 leading-relaxed">{!! $s['body'] !!}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Modules at a glance --}}
        <section>
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Modules at a glance</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($modules as $m)
                    <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-4">
                        <div class="flex items-center gap-2 mb-1.5">
                            <x-dynamic-component :component="$m['icon']" class="h-5 w-5 text-primary-500" />
                            <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $m['title'] }}</p>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">{!! $m['body'] !!}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>
