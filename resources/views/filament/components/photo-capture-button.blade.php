<div class="flex gap-2" data-photo-capture-button>
    <button
        type="button"
        onclick="window.dispatchEvent(new Event('open-photo-capture'))"
        class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 active:scale-95 transition-transform"
        title="Take a photo with your camera"
    >
        📷 Take Photo
    </button>
</div>

<script>
(function () {
    // Bound once, however many times this component is rendered on a page.
    if (window.__vxPhotoCaptureBound) return;
    window.__vxPhotoCaptureBound = true;

    function attachCapturedPhoto(event) {
        const file = event.detail?.file;
        if (!file) return;

        // Prefer an upload field inside the same card as a capture button, so
        // a page with more than one upload sends the photo to the right one.
        const scope = document.querySelector('[data-photo-capture-button]')?.closest('.fi-sc-component, .fi-section, form') ?? document;

        const input = scope.querySelector('input[type="file"][accept*="image"]')
            ?? document.querySelector('input[type="file"][accept*="image"]');

        if (!input) {
            console.warn('[photo-capture] No image upload field on this page');
            return;
        }

        const transfer = new DataTransfer();

        // Keep anything already selected — capturing a second photo should add
        // to the batch rather than replace the first.
        for (const existing of input.files ?? []) {
            transfer.items.add(existing);
        }

        transfer.items.add(file);
        input.files = transfer.files;

        input.dispatchEvent(new Event('change', { bubbles: true }));
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // The capture dispatches on window. Listening only on document — as this
    // did — never fired: an event dispatched at window does not travel down to
    // document, so every photo taken was silently discarded. Both are bound
    // now so it works whichever target a future caller uses.
    window.addEventListener('photo-captured', attachCapturedPhoto);
    document.addEventListener('photo-captured', attachCapturedPhoto);
})();
</script>
