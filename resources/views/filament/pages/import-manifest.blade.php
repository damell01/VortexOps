<x-filament-panels::page>
    <div class="space-y-6 max-w-3xl">

        {{-- ── Progress stepper ────────────────────────────────────────────── --}}
        @php
            $stages = ['upload' => 'Upload', 'processing' => 'Reading', 'verify' => 'Verify', 'done' => 'Done'];
            $stageKeys = array_keys($stages);
            $currentIdx = array_search($stage, $stageKeys);
        @endphp
        <div class="flex items-center gap-0">
            @foreach ($stages as $key => $label)
                @php
                    $idx    = array_search($key, $stageKeys);
                    $done   = $idx < $currentIdx;
                    $active = $key === $stage;
                @endphp
                <div class="flex items-center {{ $loop->last ? '' : 'flex-1' }}">
                    <div class="flex flex-col items-center">
                        <div class="h-8 w-8 rounded-full flex items-center justify-center text-xs font-semibold
                            {{ $done ? 'bg-green-500 text-white' : ($active ? 'bg-violet-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-400') }}">
                            @if ($done)
                                <x-heroicon-o-check class="h-4 w-4" />
                            @else
                                {{ $idx + 1 }}
                            @endif
                        </div>
                        <span class="mt-1 text-[11px] font-medium {{ $active ? 'text-violet-600 dark:text-violet-400' : 'text-gray-400' }}">{{ $label }}</span>
                    </div>
                    @if (! $loop->last)
                        <div class="flex-1 h-0.5 mx-2 mb-4 {{ $done ? 'bg-green-400' : 'bg-gray-200 dark:bg-gray-700' }}"></div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════
             Stage 1 — Upload
        ═══════════════════════════════════════════════════════════════════ --}}
        @if ($stage === 'upload')
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-6 space-y-5">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Upload Packing Slip</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Take a photo of the packing slip that came with the pallet, or upload a PDF.
                        AI will read it and extract every line item for you to verify before importing.
                    </p>
                    <p class="mt-2 text-xs text-gray-400">
                        Pallet: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $this->record->reference ?? "#{$this->record->id}" }}</span>
                        @if ($this->record->vendor)
                            &nbsp;·&nbsp; Vendor: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $this->record->vendor->name }}</span>
                        @endif
                    </p>
                </div>

                @if ($parseError)
                    <div class="rounded-lg bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-300 space-y-1">
                        <div class="flex items-center gap-2 font-medium">
                            <x-heroicon-o-exclamation-circle class="h-4 w-4 flex-shrink-0" />
                            {{ $parseErrorIsTimeout ? 'AI worker timed out' : 'AI parsing failed' }}
                        </div>
                        <p class="text-xs">{{ $parseError }}</p>
                        @if ($parseErrorIsTimeout)
                            <p class="text-xs text-red-500">Restart the worker: <code class="font-mono bg-red-100 dark:bg-red-900 px-1 rounded">docker compose up -d ai-worker</code></p>
                        @else
                            <p class="text-xs text-red-500">Make sure the <code class="font-mono bg-red-100 dark:bg-red-900 px-1 rounded">{{ config('services.ollama.vision_model') }}</code> model is pulled: <code class="font-mono bg-red-100 dark:bg-red-900 px-1 rounded">ollama pull {{ config('services.ollama.vision_model') }}</code></p>
                        @endif
                    </div>
                @endif

                <div
                    x-data="{
                        dragover: false,
                        preview: null,
                        isPdf: false,
                        handleFile(file) {
                            this.isPdf = file.type === 'application/pdf';
                            if (!this.isPdf) {
                                const reader = new FileReader();
                                reader.onload = e => this.preview = e.target.result;
                                reader.readAsDataURL(file);
                            } else {
                                this.preview = 'pdf';
                            }
                        }
                    }"
                    @dragover.prevent="dragover = true"
                    @dragleave.prevent="dragover = false"
                    @drop.prevent="dragover = false; if ($event.dataTransfer.files[0]) { handleFile($event.dataTransfer.files[0]); $refs.fileInput.files = $event.dataTransfer.files; $wire.upload('slipFile', $event.dataTransfer.files[0]) }"
                    class="border-2 border-dashed rounded-xl px-6 py-8 text-center transition-colors"
                    :class="dragover ? 'border-violet-500 bg-violet-50 dark:bg-violet-950' : 'border-gray-200 dark:border-gray-700'"
                >
                    {{-- Preview of selected image --}}
                    <template x-if="preview && preview !== 'pdf'">
                        <div class="mb-4">
                            <img :src="preview" class="mx-auto max-h-48 rounded-lg shadow border border-gray-200 dark:border-gray-700 object-contain" />
                        </div>
                    </template>
                    <template x-if="preview === 'pdf'">
                        <div class="mb-4 flex flex-col items-center gap-2">
                            <x-heroicon-o-document-text class="h-12 w-12 text-violet-400" />
                            <span class="text-sm text-gray-600 dark:text-gray-400">PDF selected</span>
                        </div>
                    </template>
                    <template x-if="!preview">
                        <div class="mb-4">
                            <x-heroicon-o-camera class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                        </div>
                    </template>

                    <p x-show="!preview" class="text-sm text-gray-500 dark:text-gray-400">
                        Drag &amp; drop, or tap to select
                    </p>
                    <p x-show="!preview" class="mt-1 text-xs text-gray-400">
                        Photo (JPG, PNG, WEBP) or PDF — max 20 MB
                    </p>

                    <label class="mt-4 inline-block cursor-pointer rounded-lg bg-violet-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-violet-700">
                        <span x-text="preview ? 'Change File' : 'Choose Photo or PDF'"></span>
                        <input
                            x-ref="fileInput"
                            wire:model="slipFile"
                            @change="if ($event.target.files[0]) handleFile($event.target.files[0])"
                            type="file"
                            accept="image/*,.pdf,application/pdf"
                            capture="environment"
                            class="sr-only"
                        />
                    </label>
                    <div wire:loading wire:target="slipFile" class="mt-3 text-xs text-gray-400">Uploading…</div>
                </div>

                <div class="flex items-center gap-3 pt-1">
                    <button
                        wire:click="parseSlip"
                        wire:loading.attr="disabled"
                        :disabled="{{ $slipFile ? 'false' : 'true' }}"
                        type="button"
                        class="rounded-lg bg-violet-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        <span wire:loading.remove wire:target="parseSlip">Read with AI</span>
                        <span wire:loading wire:target="parseSlip">Sending to AI…</span>
                    </button>
                    <a
                        href="{{ \App\Filament\Resources\PalletResource::getUrl('view', ['record' => $this->record]) }}"
                        class="rounded-lg border border-gray-200 dark:border-gray-700 px-5 py-2.5 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800"
                    >
                        Cancel
                    </a>
                </div>

                <p class="text-xs text-gray-400">
                    Need to enter lines manually?
                    <a href="{{ \App\Filament\Resources\PalletResource::getUrl('edit', ['record' => $this->record]) }}" class="underline text-violet-500">Edit the pallet directly.</a>
                </p>
            </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════════════════
             Stage 2 — AI Processing (polls every 3 s)
        ═══════════════════════════════════════════════════════════════════ --}}
        @if ($stage === 'processing')
            <div wire:poll.3000ms="checkProcessing">
                <div class="rounded-xl border border-violet-200 dark:border-violet-800 bg-violet-50 dark:bg-violet-950 px-6 py-10 text-center space-y-4">
                    <div class="flex justify-center">
                        <svg class="animate-spin h-10 w-10 text-violet-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-base font-semibold text-violet-900 dark:text-violet-100">Reading your packing slip…</p>
                        <p class="mt-1 text-sm text-violet-600 dark:text-violet-400">
                            AI is extracting the line items. This usually takes 15–60 seconds.
                        </p>
                    </div>
                    <p class="text-xs text-violet-400">Using model: {{ config('services.ollama.vision_model') }}</p>
                </div>
            </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════════════════
             Stage 3 — Verify & Edit
        ═══════════════════════════════════════════════════════════════════ --}}
        @if ($stage === 'verify')
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-6 space-y-5">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Verify Line Items</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        AI found <strong>{{ count($parsedLines) }}</strong> line item(s). Check everything looks right — edit, add, or remove lines before importing.
                    </p>
                </div>

                @if (count($parsedLines) === 0)
                    <div class="rounded-lg bg-amber-50 dark:bg-amber-950 border border-amber-200 dark:border-amber-800 px-4 py-3 text-sm text-amber-700 dark:text-amber-300">
                        AI couldn't extract any lines from this image. The photo may be blurry or the format isn't recognised. Add lines manually below, or go back and try a clearer photo.
                    </div>
                @endif

                {{-- Editable lines — mobile: stacked cards, desktop: compact table --}}
                <div class="space-y-2">

                    {{-- Mobile card layout (hidden on sm+) --}}
                    <div class="sm:hidden space-y-2">
                        @foreach ($parsedLines as $i => $line)
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-3 space-y-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] font-mono text-gray-400 flex-shrink-0">#{{ $i + 1 }}</span>
                                    <input
                                        wire:model="parsedLines.{{ $i }}.description"
                                        type="text"
                                        placeholder="Item description"
                                        class="flex-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-1.5 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500 min-w-0 {{ trim($line['description']) === '' ? 'border-amber-300 dark:border-amber-600' : '' }}"
                                    />
                                    <button
                                        wire:click="removeLine({{ $i }})"
                                        type="button"
                                        class="flex-shrink-0 text-gray-300 dark:text-gray-600 hover:text-red-400 dark:hover:text-red-500 focus:outline-none"
                                        title="Remove line"
                                    >
                                        <x-heroicon-o-x-mark class="h-4 w-4" />
                                    </button>
                                </div>
                                <div class="grid grid-cols-3 gap-2">
                                    <div>
                                        <label class="block text-[10px] text-gray-400 mb-0.5 uppercase">Cases</label>
                                        <input
                                            wire:model="parsedLines.{{ $i }}.case_count"
                                            type="number"
                                            min="1"
                                            placeholder="1"
                                            class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-2 py-1.5 text-sm font-mono text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-gray-400 mb-0.5 uppercase">Unit Cost</label>
                                        <input
                                            wire:model="parsedLines.{{ $i }}.unit_cost"
                                            type="text"
                                            placeholder="0.00"
                                            class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-2 py-1.5 text-sm font-mono text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-gray-400 mb-0.5 uppercase">SKU</label>
                                        <input
                                            wire:model="parsedLines.{{ $i }}.sku"
                                            type="text"
                                            placeholder="SKU"
                                            class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-2 py-1.5 text-sm font-mono text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500"
                                        />
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Desktop table layout (hidden on mobile) --}}
                    <div class="hidden sm:block space-y-2">
                        <div class="grid grid-cols-12 gap-2 px-1 text-[11px] font-medium uppercase text-gray-400">
                            <div class="col-span-5">Description</div>
                            <div class="col-span-2">Cases</div>
                            <div class="col-span-2">Unit Cost</div>
                            <div class="col-span-2">SKU</div>
                            <div class="col-span-1"></div>
                        </div>

                        @foreach ($parsedLines as $i => $line)
                            <div class="grid grid-cols-12 gap-2 items-center">
                                <div class="col-span-5">
                                    <input
                                        wire:model="parsedLines.{{ $i }}.description"
                                        type="text"
                                        placeholder="Item description"
                                        class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-1.5 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500 {{ trim($line['description']) === '' ? 'border-amber-300 dark:border-amber-600' : '' }}"
                                    />
                                </div>
                                <div class="col-span-2">
                                    <input
                                        wire:model="parsedLines.{{ $i }}.case_count"
                                        type="number"
                                        min="1"
                                        placeholder="1"
                                        class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-1.5 text-sm font-mono text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500"
                                    />
                                </div>
                                <div class="col-span-2">
                                    <input
                                        wire:model="parsedLines.{{ $i }}.unit_cost"
                                        type="text"
                                        placeholder="0.00"
                                        class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-1.5 text-sm font-mono text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500"
                                    />
                                </div>
                                <div class="col-span-2">
                                    <input
                                        wire:model="parsedLines.{{ $i }}.sku"
                                        type="text"
                                        placeholder="SKU"
                                        class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-1.5 text-sm font-mono text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500"
                                    />
                                </div>
                                <div class="col-span-1 flex justify-center">
                                    <button
                                        wire:click="removeLine({{ $i }})"
                                        type="button"
                                        class="text-gray-300 dark:text-gray-600 hover:text-red-400 dark:hover:text-red-500 focus:outline-none"
                                        title="Remove line"
                                    >
                                        <x-heroicon-o-x-mark class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <button
                        wire:click="addLine"
                        type="button"
                        class="flex items-center gap-1.5 text-sm text-violet-600 dark:text-violet-400 hover:text-violet-700 mt-2"
                    >
                        <x-heroicon-o-plus class="h-4 w-4" />
                        Add line
                    </button>
                </div>

                <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-800">
                    <button
                        wire:click="import"
                        wire:loading.attr="disabled"
                        type="button"
                        class="rounded-lg bg-violet-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="import">Import {{ count($parsedLines) }} Lines</span>
                        <span wire:loading wire:target="import">Importing…</span>
                    </button>
                    <button
                        wire:click="$set('stage', 'upload')"
                        type="button"
                        class="rounded-lg border border-gray-200 dark:border-gray-700 px-5 py-2.5 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800"
                    >
                        Try Again
                    </button>
                </div>
            </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════════════════
             Stage 4 — Done
        ═══════════════════════════════════════════════════════════════════ --}}
        @if ($stage === 'done')
            <div class="rounded-xl border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950 px-6 py-10 text-center space-y-4">
                <x-heroicon-o-check-circle class="h-12 w-12 mx-auto text-green-500" />
                <div>
                    <h2 class="text-lg font-semibold text-green-900 dark:text-green-100">Imported</h2>
                    <p class="mt-1 text-sm text-green-700 dark:text-green-300">
                        {{ $created }} line(s) added to the pallet.
                        {{ $matched }} auto-matched to existing inventory items.
                        @if ($unmatched > 0)
                            <br><strong>{{ $unmatched }} line(s) still need mapping</strong> — use the "Map Line to Item" button on the pallet.
                        @endif
                    </p>
                </div>
                <a
                    href="{{ \App\Filament\Resources\PalletResource::getUrl('view', ['record' => $this->record]) }}"
                    class="inline-block rounded-lg bg-green-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-green-700"
                >
                    Back to Pallet
                </a>
            </div>
        @endif

    </div>
</x-filament-panels::page>
