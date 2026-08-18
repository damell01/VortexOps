<div x-data="cameraPhotoCapture()" x-on:open-photo-capture.window="open()">
    {{-- Overlay --}}
    {{-- x-cloak: full-viewport overlay, inert until Alpine boots — see the
         barcode scanner component for the failure this prevents. --}}
    <div
        x-cloak
        x-show="isOpen"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
        x-on:click.self="close()"
    >
        <div class="relative w-full max-w-md rounded-2xl bg-white dark:bg-gray-900 shadow-2xl overflow-hidden">
            {{-- Header --}}
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">📷 Take Photo</h3>
                <button type="button" x-on:click="close()" class="rounded p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            {{-- Camera view or preview --}}
            <div x-show="!preview" class="relative bg-black aspect-video overflow-hidden">
                <video x-ref="videoStream" class="w-full h-full object-cover"></video>
                <div x-show="error" class="absolute inset-0 flex items-center justify-center bg-red-500/20">
                    <p x-text="error" class="text-white text-center text-sm"></p>
                </div>
            </div>

            <div x-show="preview" class="relative bg-black aspect-video overflow-hidden">
                <img x-ref="previewImg" class="w-full h-full object-cover">
            </div>

            {{-- Status/Error --}}
            <div class="px-4 py-3 space-y-2">
                <p x-show="statusText && !preview" class="text-xs text-center text-gray-600 dark:text-gray-400" x-text="statusText"></p>
                <p x-show="error" class="text-xs text-center text-red-600 dark:text-red-400 font-medium" x-text="error"></p>
            </div>

            {{-- Action Buttons --}}
            <div class="px-4 py-3 flex gap-2 border-t border-gray-200 dark:border-gray-700">
                <button
                    type="button"
                    x-show="!preview"
                    x-on:click="capturePhoto()"
                    class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 active:scale-95 transition-transform"
                >
                    📸 Capture
                </button>
                <button
                    type="button"
                    x-show="preview"
                    x-on:click="usePhoto()"
                    class="flex-1 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 active:scale-95 transition-transform"
                >
                    ✓ Use Photo
                </button>
                <button
                    type="button"
                    x-show="preview"
                    x-on:click="retakePhoto()"
                    class="flex-1 rounded-lg bg-gray-600 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 active:scale-95 transition-transform"
                >
                    🔄 Retake
                </button>
                <button
                    type="button"
                    x-on:click="close()"
                    class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"
                >
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

@verbatim
<script>
function cameraPhotoCapture() {
    return {
        isOpen: false,
        error: null,
        statusText: 'Initializing camera…',
        preview: false,
        stream: null,
        canvas: null,

        async open() {
            this.isOpen = true;
            this.error = null;
            this.statusText = 'Initializing camera…';
            this.preview = false;

            await this.$nextTick();
            await this.startCamera();
        },

        async startCamera() {
            try {
                const video = this.$refs.videoStream;
                if (!video) return;

                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: 'environment',
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: false
                });

                video.srcObject = this.stream;
                video.play();
                this.statusText = '📷 Point camera and tap Capture';
            } catch (err) {
                console.error('[photo-capture]', err);
                if (err.name === 'NotAllowedError') {
                    this.error = '❌ Camera permission denied';
                } else if (err.name === 'NotFoundError') {
                    this.error = '❌ No camera found';
                } else {
                    this.error = '❌ Camera error: ' + err.message;
                }
            }
        },

        capturePhoto() {
            try {
                const video = this.$refs.videoStream;
                if (!video || !video.srcObject) return;

                // Create canvas and capture video frame
                this.canvas = document.createElement('canvas');
                this.canvas.width = video.videoWidth;
                this.canvas.height = video.videoHeight;
                const ctx = this.canvas.getContext('2d');
                ctx.drawImage(video, 0, 0);

                // Show preview
                const previewImg = this.$refs.previewImg;
                previewImg.src = this.canvas.toDataURL('image/jpeg', 0.95);
                this.preview = true;
                this.statusText = '';
            } catch (err) {
                console.error('[photo-capture] capture error:', err);
                this.error = '❌ Failed to capture photo';
            }
        },

        usePhoto() {
            if (!this.canvas) return;

            // Convert canvas to blob and create file
            this.canvas.toBlob((blob) => {
                const timestamp = new Date().getTime();
                const file = new File(
                    [blob],
                    `photo-${timestamp}.jpg`,
                    { type: 'image/jpeg' }
                );

                // Dispatch event with the captured file
                window.dispatchEvent(new CustomEvent('photo-captured', {
                    detail: { file }
                }));

                this.close();
            }, 'image/jpeg', 0.95);
        },

        retakePhoto() {
            this.preview = false;
            this.statusText = '📷 Point camera and tap Capture';
        },

        async close() {
            this.isOpen = false;
            this.preview = false;
            this.error = null;
            this.statusText = '';

            // Stop video stream
            if (this.stream) {
                this.stream.getTracks().forEach(track => track.stop());
                this.stream = null;
            }

            // Clear canvas and preview
            this.canvas = null;
            if (this.$refs.previewImg) {
                this.$refs.previewImg.src = '';
            }
        }
    };
}
</script>
@endverbatim
