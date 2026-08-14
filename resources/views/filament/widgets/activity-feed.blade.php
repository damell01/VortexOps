<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Recent Activity</x-slot>
        <x-slot name="afterHeader">
            <span class="text-xs text-gray-400 dark:text-gray-500">Last 20 events</span>
        </x-slot>

        @if ($activities->isEmpty())
            <div class="py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                No activity recorded yet.
            </div>
        @else
            <div class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($activities as $activity)
                    @php
                        $subject = $activity->subject_type
                            ? class_basename($activity->subject_type)
                            : null;

                        $desc = $activity->description;
                        $causer = $activity->causer?->name ?? 'System';
                        $log = $activity->log_name;

                        $icon = match ($log) {
                            'payout'            => 'heroicon-o-currency-dollar',
                            'show'              => 'heroicon-o-video-camera',
                            'deduction_request' => 'heroicon-o-clipboard-document-check',
                            'inventory'         => 'heroicon-o-cube',
                            'pallet'            => 'heroicon-o-inbox-stack',
                            'streamer'          => 'heroicon-o-user-group',
                            default             => 'heroicon-o-bolt',
                        };

                        $color = match ($log) {
                            'payout'            => 'text-amber-500',
                            'show'              => 'text-violet-500',
                            'deduction_request' => 'text-sky-500',
                            'inventory'         => 'text-emerald-500',
                            'pallet'            => 'text-orange-500',
                            'streamer'          => 'text-pink-500',
                            default             => 'text-gray-400',
                        };

                        $changes = $activity->properties->get('attributes', []);
                        $old = $activity->properties->get('old', []);
                    @endphp

                    <div class="flex items-start gap-3 py-3">
                        <div class="mt-0.5 shrink-0">
                            <x-dynamic-component :component="$icon" class="h-4 w-4 {{ $color }}" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ ucfirst($desc) }}
                                    @if ($subject)
                                        <span class="text-gray-400 dark:text-gray-500 font-normal">on {{ $subject }}</span>
                                    @endif
                                </span>
                                @if ($log)
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold bg-gray-100 dark:bg-white/10 text-gray-600 dark:text-gray-300">
                                        {{ $log }}
                                    </span>
                                @endif
                            </div>

                            @if (!empty($changes))
                                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-0.5">
                                    @foreach ($changes as $field => $newVal)
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ str_replace('_', ' ', $field) }}:</span>
                                            @if (isset($old[$field]))
                                                <span class="line-through text-gray-400 dark:text-gray-500">{{ Str::limit((string) $old[$field], 30) }}</span>
                                                →
                                            @endif
                                            {{ Str::limit((string) $newVal, 30) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                {{ $causer }}
                                &middot;
                                {{ $activity->created_at->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
