<div id="feedback-widget-root"
     x-data="feedbackWidget()"
     @keydown.escape.window="if (open) close()">

    {{-- Floating trigger button --}}
    <button
        id="feedback-trigger-btn"
        @click="openWidget"
        x-show="!open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        title="Leave feedback"
        class="fixed bottom-6 right-6 z-40 flex items-center gap-2 rounded-full bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg ring-1 ring-primary-500/20 hover:bg-primary-500 active:scale-95 transition-all duration-150">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
        </svg>
        Feedback
    </button>

    {{-- Backdrop + modal --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
        @click.self="close">

        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="w-full max-w-2xl rounded-2xl bg-white dark:bg-gray-900 shadow-2xl ring-1 ring-gray-200 dark:ring-gray-700 overflow-hidden flex flex-col"
            style="max-height: 90vh;">

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                    </svg>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Leave Feedback</h2>
                </div>
                <button @click="close" class="rounded-lg p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Success state --}}
            @if ($submitted)
                <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
                    <div class="w-14 h-14 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">Feedback Submitted!</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Our team has been notified and will review your feedback.</p>
                    <button
                        wire:click="resetWidget"
                        @click="close"
                        class="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-500 transition-colors">
                        Done
                    </button>
                </div>
            @else
                {{-- Step tabs --}}
                <div class="flex border-b border-gray-200 dark:border-gray-700 shrink-0">
                    <div :class="step === 'annotate' || step === 'capturing' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-400 dark:text-gray-500'"
                         class="flex-1 flex items-center justify-center gap-1.5 py-2.5 text-xs font-medium border-b-2 transition-colors">
                        <span class="w-4 h-4 rounded-full text-[10px] font-bold flex items-center justify-center"
                              :class="step === 'annotate' || step === 'capturing' ? 'bg-primary-100 dark:bg-primary-900 text-primary-600 dark:text-primary-400' : 'bg-gray-100 dark:bg-gray-800 text-gray-400'">1</span>
                        Annotate Screenshot
                    </div>
                    <div :class="step === 'form' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-400 dark:text-gray-500'"
                         class="flex-1 flex items-center justify-center gap-1.5 py-2.5 text-xs font-medium border-b-2 transition-colors">
                        <span class="w-4 h-4 rounded-full text-[10px] font-bold flex items-center justify-center"
                              :class="step === 'form' ? 'bg-primary-100 dark:bg-primary-900 text-primary-600 dark:text-primary-400' : 'bg-gray-100 dark:bg-gray-800 text-gray-400'">2</span>
                        Add Details
                    </div>
                </div>

                {{-- Capturing spinner --}}
                <div x-show="step === 'capturing'" class="flex flex-col items-center justify-center py-16">
                    <div class="w-10 h-10 rounded-full border-4 border-primary-500 border-t-transparent animate-spin mb-3"></div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Capturing screenshot…</p>
                </div>

                {{-- Step 1: Annotation --}}
                <div x-show="step === 'annotate'" class="flex flex-col overflow-hidden">
                    {{-- Toolbar --}}
                    <div class="flex items-center gap-2 px-4 py-2.5 border-b border-gray-100 dark:border-gray-800 flex-wrap shrink-0">
                        {{-- Tool buttons --}}
                        <div class="flex items-center gap-0.5 rounded-lg border border-gray-200 dark:border-gray-700 p-0.5">
                            <template x-for="t in tools" :key="t.id">
                                <button
                                    @click="setTool(t.id)"
                                    :title="t.label"
                                    :class="tool === t.id
                                        ? 'bg-primary-100 dark:bg-primary-900 text-primary-600 dark:text-primary-400'
                                        : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800'"
                                    class="p-1.5 rounded-md transition-colors"
                                    x-html="t.icon">
                                </button>
                            </template>
                        </div>

                        {{-- Line width --}}
                        <div class="flex items-center gap-1 rounded-lg border border-gray-200 dark:border-gray-700 p-0.5">
                            <template x-for="w in lineWidths" :key="w.v">
                                <button
                                    @click="lineWidth = w.v"
                                    :class="lineWidth === w.v ? 'bg-primary-100 dark:bg-primary-900 text-primary-600' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800'"
                                    :title="w.label"
                                    class="p-1.5 rounded-md transition-colors flex items-center justify-center">
                                    <span :style="`width:12px; height:${w.v}px; background:currentColor; display:block; border-radius:2px`"></span>
                                </button>
                            </template>
                        </div>

                        {{-- Color swatches --}}
                        <div class="flex items-center gap-1">
                            <template x-for="c in colors" :key="c">
                                <button
                                    @click="setColor(c)"
                                    :style="`background:${c}`"
                                    :class="color === c ? 'ring-2 ring-offset-2 ring-gray-400 dark:ring-offset-gray-900' : ''"
                                    class="w-5 h-5 rounded-full border border-white dark:border-gray-600 shadow-sm transition-all shrink-0">
                                </button>
                            </template>
                        </div>

                        {{-- Undo --}}
                        <button
                            @click="undo"
                            :disabled="history.length <= 1"
                            class="ml-auto flex items-center gap-1 rounded-md px-2 py-1.5 text-xs font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 disabled:opacity-40 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                            </svg>
                            Undo
                        </button>
                    </div>

                    {{-- Canvas scroll container --}}
                    <div class="overflow-auto bg-gray-50 dark:bg-gray-950 border-b border-gray-200 dark:border-gray-700"
                         style="max-height: 340px; min-height: 140px;">
                        <canvas
                            id="annotation-canvas"
                            x-ref="canvas"
                            class="block mx-auto"
                            style="cursor: crosshair; touch-action: none; display: block;"
                            @mousedown="startDraw($event)"
                            @mousemove="draw($event)"
                            @mouseup="endDraw($event)"
                            @mouseleave="endDraw($event)"
                            @touchstart.prevent="startDraw(touchToMouse($event))"
                            @touchmove.prevent="draw(touchToMouse($event))"
                            @touchend.prevent="endDraw(touchToMouse($event))">
                        </canvas>
                    </div>

                    <div class="flex items-center justify-between px-4 py-3 shrink-0">
                        <button @click="close" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                            Cancel
                        </button>
                        <button @click="goToForm" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500 transition-colors flex items-center gap-1.5">
                            Next
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Step 2: Form --}}
                <div x-show="step === 'form'" class="overflow-y-auto">
                    <div class="p-5 space-y-4">
                        {{-- Screenshot thumbnail --}}
                        <div x-show="screenshotDataUrl" class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 max-h-40 overflow-y-auto bg-gray-50 dark:bg-gray-950">
                            <img :src="screenshotDataUrl" class="w-full" alt="Your annotated screenshot">
                        </div>

                        {{-- Title --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                What's the issue? <span class="text-red-500">*</span>
                            </label>
                            <input
                                wire:model.blur="title"
                                type="text"
                                placeholder="e.g. Button doesn't save, wrong total shown…"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                            @error('title')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Description --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                More details <span class="text-gray-400 font-normal">(optional)</span>
                            </label>
                            <textarea
                                wire:model.blur="description"
                                rows="3"
                                placeholder="Steps to reproduce, expected vs actual behavior…"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none resize-none transition-colors">
                            </textarea>
                        </div>

                        {{-- Priority --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Priority</label>
                            <div class="flex gap-4">
                                @foreach(['low' => ['Low', 'text-blue-500'], 'medium' => ['Medium', 'text-amber-500'], 'high' => ['High', 'text-red-500']] as $val => [$label, $color])
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input wire:model="priority" type="radio" value="{{ $val }}"
                                               class="text-primary-600 focus:ring-primary-500">
                                        <span class="text-sm {{ $color }} font-medium">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between px-5 py-4 border-t border-gray-200 dark:border-gray-700 shrink-0">
                        <button @click="step = 'annotate'" class="flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Back
                        </button>
                        <div class="flex items-center gap-2">
                            <button @click="close" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                                Cancel
                            </button>
                            <button
                                @click="doSubmit"
                                :disabled="submitting"
                                class="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-500 disabled:opacity-50 flex items-center gap-2 transition-colors">
                                <span x-show="submitting" class="inline-block w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                Submit Feedback
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@once
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js" defer></script>
@endonce

<script>
function feedbackWidget() {
    return {
        open:            false,
        step:            'idle',
        submitting:      false,

        // Annotation tools
        tool:      'pen',
        color:     '#ef4444',
        lineWidth: 3,
        colors:    ['#ef4444', '#3b82f6', '#22c55e', '#f59e0b', '#8b5cf6', '#000000', '#ffffff'],
        lineWidths: [
            { v: 2, label: 'Thin' },
            { v: 4, label: 'Medium' },
            { v: 7, label: 'Thick' },
        ],
        tools: [
            {
                id: 'pen', label: 'Pen (freehand)',
                icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>`,
            },
            {
                id: 'rect', label: 'Rectangle',
                icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><rect x="3" y="3" width="18" height="18" rx="1" stroke-width="2" stroke="currentColor" fill="none"/></svg>`,
            },
            {
                id: 'arrow', label: 'Arrow',
                icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>`,
            },
            {
                id: 'highlight', label: 'Highlight',
                icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>`,
            },
        ],

        // Canvas state
        canvas:           null,
        ctx:              null,
        history:          [],
        isDrawing:        false,
        startX:           0,
        startY:           0,
        screenshotDataUrl: null,

        async openWidget() {
            this.step = 'capturing';
            this.open = true;
            await this.$nextTick();

            const btn = document.getElementById('feedback-trigger-btn');
            const root = document.getElementById('feedback-widget-root');

            try {
                // Hide widget during capture
                if (root) root.style.display = 'none';
                await new Promise(r => setTimeout(r, 80));

                const htmlCanvas = await html2canvas(document.body, {
                    logging:        false,
                    useCORS:        true,
                    allowTaint:     true,
                    scale:          Math.min(window.devicePixelRatio || 1, 1.5),
                });

                if (root) root.style.display = '';

                this.screenshotDataUrl = htmlCanvas.toDataURL('image/png');
                await this.$nextTick();
                this.initCanvas();
                this.step = 'annotate';

            } catch (e) {
                console.warn('Screenshot capture failed:', e);
                if (root) root.style.display = '';
                this.screenshotDataUrl = null;
                this.step = 'form';
            }
        },

        close() {
            this.open      = false;
            this.step      = 'idle';
            this.history   = [];
            this.isDrawing = false;
            this.submitting = false;
            this.canvas    = null;
            this.ctx       = null;
        },

        setTool(t)  { this.tool  = t; },
        setColor(c) { this.color = c; },

        initCanvas() {
            this.canvas = document.getElementById('annotation-canvas');
            if (!this.canvas || !this.screenshotDataUrl) return;

            this.ctx = this.canvas.getContext('2d');
            const img = new Image();
            img.onload = () => {
                const maxW  = 640;
                const scale = Math.min(1, maxW / img.width);
                const w     = Math.round(img.width * scale);
                const h     = Math.round(img.height * scale);
                this.canvas.width  = w;
                this.canvas.height = h;
                this.canvas.style.width  = w + 'px';
                this.canvas.style.height = h + 'px';
                this.ctx.drawImage(img, 0, 0, w, h);
                this.saveHistory();
            };
            img.src = this.screenshotDataUrl;
        },

        saveHistory() {
            if (!this.ctx || !this.canvas) return;
            this.history.push(this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height));
            if (this.history.length > 25) this.history.shift();
        },

        undo() {
            if (this.history.length <= 1) return;
            this.history.pop();
            this.ctx.putImageData(this.history[this.history.length - 1], 0, 0);
        },

        getPos(event) {
            const rect   = this.canvas.getBoundingClientRect();
            const scaleX = this.canvas.width  / rect.width;
            const scaleY = this.canvas.height / rect.height;
            return {
                x: (event.clientX - rect.left) * scaleX,
                y: (event.clientY - rect.top)  * scaleY,
            };
        },

        touchToMouse(event) {
            const t = event.touches[0] || event.changedTouches[0];
            return { clientX: t.clientX, clientY: t.clientY };
        },

        startDraw(event) {
            if (!this.canvas) return;
            this.isDrawing = true;
            const pos = this.getPos(event);
            this.startX = pos.x;
            this.startY = pos.y;

            this.ctx.strokeStyle = this.tool === 'highlight' ? this.color + '80' : this.color;
            this.ctx.fillStyle   = this.tool === 'highlight' ? this.color + '40' : this.color;
            this.ctx.lineWidth   = this.tool === 'highlight' ? Math.max(this.lineWidth * 6, 20) : this.lineWidth;
            this.ctx.lineCap     = 'round';
            this.ctx.lineJoin    = 'round';
            this.ctx.globalAlpha = this.tool === 'highlight' ? 0.4 : 1;

            if (this.tool === 'pen') {
                this.ctx.beginPath();
                this.ctx.moveTo(pos.x, pos.y);
            }
        },

        draw(event) {
            if (!this.isDrawing || !this.canvas) return;
            const pos  = this.getPos(event);
            const prev = this.history[this.history.length - 1];

            if (this.tool === 'pen') {
                this.ctx.lineTo(pos.x, pos.y);
                this.ctx.stroke();
            } else {
                this.ctx.putImageData(prev, 0, 0);
                this.ctx.strokeStyle = this.color;
                this.ctx.lineWidth   = this.lineWidth;
                this.ctx.globalAlpha = 1;

                if (this.tool === 'rect') {
                    this.ctx.strokeRect(this.startX, this.startY, pos.x - this.startX, pos.y - this.startY);
                } else if (this.tool === 'highlight') {
                    this.ctx.globalAlpha = 0.35;
                    this.ctx.fillStyle   = this.color;
                    this.ctx.fillRect(this.startX, this.startY, pos.x - this.startX, pos.y - this.startY);
                    this.ctx.globalAlpha = 1;
                } else if (this.tool === 'arrow') {
                    this.drawArrow(this.startX, this.startY, pos.x, pos.y);
                }
            }
        },

        endDraw(event) {
            if (!this.isDrawing || !this.canvas) return;
            this.isDrawing = false;
            this.ctx.globalAlpha = 1;

            if (this.tool !== 'pen') {
                const pos  = this.getPos(event);
                const prev = this.history[this.history.length - 1];
                this.ctx.putImageData(prev, 0, 0);

                this.ctx.strokeStyle = this.color;
                this.ctx.lineWidth   = this.lineWidth;

                if (this.tool === 'rect') {
                    this.ctx.strokeRect(this.startX, this.startY, pos.x - this.startX, pos.y - this.startY);
                } else if (this.tool === 'highlight') {
                    this.ctx.globalAlpha = 0.35;
                    this.ctx.fillStyle   = this.color;
                    this.ctx.fillRect(this.startX, this.startY, pos.x - this.startX, pos.y - this.startY);
                    this.ctx.globalAlpha = 1;
                } else if (this.tool === 'arrow') {
                    this.drawArrow(this.startX, this.startY, pos.x, pos.y);
                }
            }

            this.saveHistory();
        },

        drawArrow(x1, y1, x2, y2) {
            const headLen = Math.max(12, this.lineWidth * 3);
            const angle   = Math.atan2(y2 - y1, x2 - x1);

            this.ctx.beginPath();
            this.ctx.moveTo(x1, y1);
            this.ctx.lineTo(x2, y2);
            this.ctx.stroke();

            this.ctx.beginPath();
            this.ctx.moveTo(x2, y2);
            this.ctx.lineTo(
                x2 - headLen * Math.cos(angle - Math.PI / 6),
                y2 - headLen * Math.sin(angle - Math.PI / 6),
            );
            this.ctx.lineTo(
                x2 - headLen * Math.cos(angle + Math.PI / 6),
                y2 - headLen * Math.sin(angle + Math.PI / 6),
            );
            this.ctx.closePath();
            this.ctx.fill();
        },

        goToForm() {
            if (this.canvas) {
                this.screenshotDataUrl = this.canvas.toDataURL('image/png');
            }
            this.step = 'form';
        },

        async doSubmit() {
            this.submitting = true;
            try {
                const dataUrl = this.canvas ? this.canvas.toDataURL('image/png') : '';
                @this.call('submitWithScreenshot', dataUrl, window.location.href);
            } catch (e) {
                console.error('Submit failed:', e);
                this.submitting = false;
            }
        },
    };
}
</script>
