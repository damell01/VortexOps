<div class="space-y-6">
    {{-- Workflow Progress --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Workflow Status</h3>

        <div class="flex items-center justify-between mb-8">
            {{-- Step 1: Streamer Review --}}
            <div class="flex flex-col items-center flex-1">
                <div @class([
                    'w-12 h-12 rounded-full flex items-center justify-center font-bold text-white mb-2 transition',
                    'bg-green-600' => $log->isSubmitted(),
                    'bg-primary-600' => !$log->isSubmitted() && $currentStep === 'streamer_review',
                    'bg-gray-300 dark:bg-gray-600' => $log->isSubmitted() && $currentStep !== 'streamer_review',
                ])>
                    @if($log->isSubmitted())
                        ✓
                    @else
                        1
                    @endif
                </div>
                <p class="text-sm font-medium text-gray-900 dark:text-white text-center">Streamer<br>Review</p>
                @if($log->streamer_reviewed_at)
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $log->streamer_reviewed_at->format('M j, Y') }}</p>
                @endif
            </div>

            <div class="flex-1 h-1 bg-gray-300 dark:bg-gray-600 mx-2 mt-6"></div>

            {{-- Step 2: Admin Review --}}
            <div class="flex flex-col items-center flex-1">
                <div @class([
                    'w-12 h-12 rounded-full flex items-center justify-center font-bold text-white mb-2 transition',
                    'bg-green-600' => $log->reviewed_at,
                    'bg-primary-600' => !$log->reviewed_at && $currentStep === 'admin_review',
                    'bg-gray-300 dark:bg-gray-600' => $log->reviewed_at === null && $currentStep !== 'admin_review',
                ])>
                    @if($log->reviewed_at)
                        ✓
                    @else
                        2
                    @endif
                </div>
                <p class="text-sm font-medium text-gray-900 dark:text-white text-center">Admin<br>Review</p>
                @if($log->reviewed_at)
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $log->reviewed_at->format('M j, Y') }}</p>
                @endif
            </div>

            @if($needsFulfillment)
                <div class="flex-1 h-1 bg-gray-300 dark:bg-gray-600 mx-2 mt-6"></div>

                {{-- Step 3: Fulfillment Review --}}
                <div class="flex flex-col items-center flex-1">
                    <div @class([
                        'w-12 h-12 rounded-full flex items-center justify-center font-bold text-white mb-2 transition',
                        'bg-green-600' => $log->fulfillment_reviewed_at,
                        'bg-primary-600' => !$log->fulfillment_reviewed_at && $currentStep === 'fulfillment_review',
                        'bg-gray-300 dark:bg-gray-600' => $log->fulfillment_reviewed_at === null && $currentStep !== 'fulfillment_review',
                    ])>
                        @if($log->fulfillment_reviewed_at)
                            ✓
                        @else
                            3
                        @endif
                    </div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white text-center">Fulfillment<br>Review</p>
                    @if($log->fulfillment_reviewed_at)
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $log->fulfillment_reviewed_at->format('M j, Y') }}</p>
                    @endif
                </div>
            @endif
        </div>

        {{-- Current Status Badge --}}
        <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
            <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">Current Status</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">
                    @switch($currentStep)
                        @case('streamer_review')
                            📝 Awaiting Streamer Review
                            @break
                        @case('admin_review')
                            👨‍💼 Awaiting Admin Review
                            @break
                        @case('fulfillment_review')
                            📦 Awaiting Fulfillment Review
                            @break
                        @case('completed')
                            ✓ Completed
                            @break
                    @endswitch
                </p>
            </div>
            <span @class([
                'px-4 py-2 rounded-full font-medium text-sm',
                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' => !$log->reviewed_at,
                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' => $log->reviewed_at && !$needsFulfillment,
                'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' => $log->reviewed_at && $needsFulfillment && !$log->fulfillment_reviewed_at,
                'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200' => $log->fulfillment_reviewed_at,
            ])>
                @if(!$log->reviewed_at)
                    🔄 In Progress
                @elseif($log->reviewed_at && !$needsFulfillment)
                    ✓ Completed
                @elseif($needsFulfillment && !$log->fulfillment_reviewed_at)
                    🔄 Fulfillment Pending
                @else
                    ✓ All Complete
                @endif
            </span>
        </div>
    </div>

    {{-- Summary Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-900/10 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
            <p class="text-sm text-gray-600 dark:text-gray-400">Revenue</p>
            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">${{ number_format((float) $log->gross_revenue, 2) }}</p>
        </div>
        <div class="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-900/10 rounded-lg p-4 border border-purple-200 dark:border-purple-800">
            <p class="text-sm text-gray-600 dark:text-gray-400">Product Cost</p>
            <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">${{ number_format((float) $log->product_cost, 2) }}</p>
        </div>
        <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-900/10 rounded-lg p-4 border border-green-200 dark:border-green-800">
            <p class="text-sm text-gray-600 dark:text-gray-400">Profit Share</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400">${{ number_format($log->profitShareAmount(), 2) }}</p>
        </div>
        <div class="bg-gradient-to-br from-amber-50 to-amber-100 dark:from-amber-900/20 dark:to-amber-900/10 rounded-lg p-4 border border-amber-200 dark:border-amber-800">
            <p class="text-sm text-gray-600 dark:text-gray-400">Items</p>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $log->show?->orders()->count() ?? 0 }}</p>
        </div>
    </div>

    {{-- Actions Section --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            @switch($currentStep)
                @case('streamer_review')
                    📝 Review & Submit Report
                    @break
                @case('admin_review')
                    👨‍💼 Admin Review
                    @break
                @case('fulfillment_review')
                    📦 Fulfillment Review
                    @break
                @case('completed')
                    ✓ All Steps Complete
                    @break
            @endswitch
        </h3>

        @switch($currentStep)
            @case('streamer_review')
                <div class="space-y-4">
                    <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            ℹ️ Please review the streamer log information below and submit when everything is correct.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hours Streamed</label>
                            <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg text-gray-900 dark:text-white font-semibold">
                                {{ number_format((float) $log->hours_streamed, 2) }} hrs
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Shipments</label>
                            <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg text-gray-900 dark:text-white font-semibold">
                                {{ $log->number_of_shipments ?? 0 }}
                            </div>
                        </div>
                    </div>

                    <button
                        wire:click="submitStreamerReview"
                        wire:loading.attr="disabled"
                        {{ $canSubmit ? '' : 'disabled' }}
                        @class([
                            'w-full py-3 px-4 rounded-lg font-semibold transition',
                            'bg-primary-600 hover:bg-primary-700 text-white' => $canSubmit,
                            'bg-gray-300 dark:bg-gray-700 text-gray-500 dark:text-gray-400 cursor-not-allowed' => !$canSubmit,
                        ])
                    >
                        {{ $isSubmitting ? '⏳ Submitting...' : '✓ Submit Report' }}
                    </button>
                </div>
                @break

            @case('admin_review')
                <div class="space-y-4">
                    <div class="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            ℹ️ Review the streamer's submitted report and approve or request changes.
                        </p>
                    </div>

                    @if($canApprove)
                        <div class="flex gap-3">
                            <button
                                wire:click="approveByAdmin"
                                wire:loading.attr="disabled"
                                class="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-4 rounded-lg transition"
                            >
                                {{ $isSubmitting ? '⏳ Approving...' : '✓ Approve Report' }}
                            </button>
                            <button
                                wire:click="rejectByAdmin"
                                wire:loading.attr="disabled"
                                class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-4 rounded-lg transition"
                            >
                                {{ $isSubmitting ? '⏳ Rejecting...' : '✗ Request Changes' }}
                            </button>
                        </div>
                    @else
                        <div class="p-4 bg-gray-100 dark:bg-gray-700 rounded-lg text-gray-700 dark:text-gray-300">
                            Only admins can approve reports.
                        </div>
                    @endif
                </div>
                @break

            @case('fulfillment_review')
                <div class="space-y-4">
                    <div class="p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg border border-indigo-200 dark:border-indigo-800">
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            ℹ️ This show requires fulfillment review (PWE/Labels payout type). Confirm the PWE and label counts.
                        </p>
                    </div>

                    @if($canFulfillment)
                        <button
                            wire:click="approveFulfillment"
                            wire:loading.attr="disabled"
                            class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 px-4 rounded-lg transition"
                        >
                            {{ $isSubmitting ? '⏳ Approving...' : '✓ Approve Fulfillment' }}
                        </button>
                    @else
                        <div class="p-4 bg-gray-100 dark:bg-gray-700 rounded-lg text-gray-700 dark:text-gray-300">
                            Only fulfillment admins can approve fulfillment reviews.
                        </div>
                    @endif
                </div>
                @break

            @case('completed')
                <div class="flex items-center gap-4 p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                    <span class="text-4xl">✓</span>
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-white">All Steps Complete</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">This streamer log has been fully processed and approved.</p>
                    </div>
                </div>
                @break
        @endswitch
    </div>

    {{-- Timeline --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Timeline</h3>

        <div class="space-y-4">
            {{-- Created --}}
            <div class="flex gap-4">
                <div class="flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-blue-600 dark:text-blue-400 font-bold">
                        📝
                    </div>
                    <div class="w-0.5 h-12 bg-gray-300 dark:bg-gray-600 mt-2"></div>
                </div>
                <div class="flex-1 pt-2">
                    <p class="font-semibold text-gray-900 dark:text-white">Report Created</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $log->created_at->format('M j, Y g:i A') }}</p>
                </div>
            </div>

            {{-- Submitted --}}
            @if($log->submitted_at)
                <div class="flex gap-4">
                    <div class="flex flex-col items-center">
                        <div class="w-10 h-10 rounded-full bg-yellow-100 dark:bg-yellow-900 flex items-center justify-center text-yellow-600 dark:text-yellow-400 font-bold">
                            ✉️
                        </div>
                        <div class="w-0.5 h-12 bg-gray-300 dark:bg-gray-600 mt-2"></div>
                    </div>
                    <div class="flex-1 pt-2">
                        <p class="font-semibold text-gray-900 dark:text-white">Submitted by Streamer</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $log->submitted_at->format('M j, Y g:i A') }}</p>
                    </div>
                </div>
            @endif

            {{-- Admin Review --}}
            @if($log->reviewed_at)
                <div class="flex gap-4">
                    <div class="flex flex-col items-center">
                        <div class="w-10 h-10 rounded-full bg-purple-100 dark:bg-purple-900 flex items-center justify-center text-purple-600 dark:text-purple-400 font-bold">
                            👨‍💼
                        </div>
                        <div class="w-0.5 h-12 bg-gray-300 dark:bg-gray-600 mt-2"></div>
                    </div>
                    <div class="flex-1 pt-2">
                        <p class="font-semibold text-gray-900 dark:text-white">Approved by Admin</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $log->reviewed_at->format('M j, Y g:i A') }}
                            @if($log->reviewedBy)
                                by {{ $log->reviewedBy->name ?? $log->reviewedBy->email }}
                            @endif
                        </p>
                    </div>
                </div>
            @endif

            {{-- Fulfillment Review --}}
            @if($log->fulfillment_reviewed_at)
                <div class="flex gap-4">
                    <div class="flex flex-col items-center">
                        <div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center text-green-600 dark:text-green-400 font-bold">
                            📦
                        </div>
                    </div>
                    <div class="flex-1 pt-2">
                        <p class="font-semibold text-gray-900 dark:text-white">Fulfillment Approved</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $log->fulfillment_reviewed_at->format('M j, Y g:i A') }}
                            @if($log->fulfillmentReviewedBy)
                                by {{ $log->fulfillmentReviewedBy->name ?? $log->fulfillmentReviewedBy->email }}
                            @endif
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
