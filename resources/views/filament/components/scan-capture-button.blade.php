{{-- A camera button for any form that asks for a barcode.

     The scanner overlay is rendered once for the whole panel and opened by a
     window event, and it fills whichever input was focused when it opened. So
     the work here is picking the right input and focusing it first — otherwise
     the scan lands in whatever the browser happened to have focus on, or
     nowhere at all.

     Scoped to the nearest modal or form so a page with several barcode fields
     sends the scan to the one the button belongs to. --}}
<div class="vx-scan-capture" data-scan-capture-button>
    <button
        type="button"
        x-data
        @click="
            const scope = $el.closest('.fi-modal-window, .fi-modal, form, .fi-sc-component') ?? document;

            // Prefer a field that says what it is; otherwise the first text
            // box in scope, which is the barcode in every current caller.
            const input =
                scope.querySelector('input[placeholder*=&quot;Scan&quot; i]') ??
                scope.querySelector('input[id*=&quot;code&quot; i]:not([type=&quot;hidden&quot;])') ??
                scope.querySelector('input[type=&quot;text&quot;]');

            if (input) input.focus();

            window.dispatchEvent(new Event('open-camera-scanner'));
        "
        class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 active:scale-95 transition-transform"
        title="Scan with your camera"
    >
        📷 Scan with camera
    </button>
</div>
