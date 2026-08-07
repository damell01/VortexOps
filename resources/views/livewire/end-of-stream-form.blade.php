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
                    @if($log->approval_status === 'rejected')
                        <div class="p-4 bg-orange-50 dark:bg-orange-900/20 rounded-lg border border-orange-200 dark:border-orange-800">
                            <p class="text-sm font-medium text-orange-900 dark:text-orange-100 mb-1">
                                🔄 Changes Requested
                            </p>
                            <p class="text-sm text-orange-800 dark:text-orange-200">
                                {{ $log->approval_notes ?? 'Admin has requested changes to your submission. Please review and resubmit.' }}
                            </p>
                        </div>
                    @endif

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
                                @click="openRejectModal"
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

    {{-- Compact Timeline --}}
    <div class="bg-gray-50 dark:bg-gray-900/30 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
        <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Timeline</h4>
        <div class="space-y-2 text-sm">
            <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                <span>📝</span>
                <span>Created {{ $log->created_at->format('M j, Y') }}</span>
            </div>
            @if($log->submitted_at)
                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                    <span>✉️</span>
                    <span>Submitted {{ $log->submitted_at->format('M j, Y') }}</span>
                </div>
            @endif
            @if($log->reviewed_at)
                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                    <span>👨‍💼</span>
                    <span>Approved {{ $log->reviewed_at->format('M j, Y') }}@if($log->reviewedBy) by {{ $log->reviewedBy->name ?? $log->reviewedBy->email }}@endif</span>
                </div>
            @endif
            @if($log->fulfillment_reviewed_at)
                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                    <span>📦</span>
                    <span>Fulfillment {{ $log->fulfillment_reviewed_at->format('M j, Y') }}@if($log->fulfillmentReviewedBy) by {{ $log->fulfillmentReviewedBy->name ?? $log->fulfillmentReviewedBy->email }}@endif</span>
                </div>
            @endif
        </div>
    </div>

    {{-- Request Changes Modal --}}
    <div
        x-data="{
            modalOpen: false,
            rejectionNotes: '',
            openRejectModal() {
                this.modalOpen = true;
                this.rejectionNotes = '';
            },
            closeRejectModal() {
                this.modalOpen = false;
                this.rejectionNotes = '';
            },
            submitReject() {
                @this.rejectByAdminWithNotes(this.rejectionNotes);
                this.closeRejectModal();
            }
        }"
        @keydown.escape="closeRejectModal()"
    >
        <div
            x-show="modalOpen"
            x-transition
            style="display: none;"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
            @click.self="closeRejectModal()"
        >
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg max-w-md w-full" @click.stop>
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Request Changes</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Add a note explaining what needs to be revised.</p>
                </div>
                <div class="px-6 py-4">
                    <textarea
                        x-model="rejectionNotes"
                        placeholder="E.g., Please verify the item quantities and resubmit..."
                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-red-500 focus:border-transparent outline-none"
                        rows="4"
                    ></textarea>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex gap-3 justify-end">
                    <button
                        @click="closeRejectModal()"
                        class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition"
                    >
                        Cancel
                    </button>
                    <button
                        @click="submitReject()"
                        :disabled="!rejectionNotes.trim()"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white rounded-lg transition font-medium"
                    >
                        Send Request
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
