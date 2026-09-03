{{--
    iOS Safari reports the keyboard-sized visual viewport a few frames after a
    searchable Filament select receives focus. The first render can therefore
    be positioned using the old full-screen height and land underneath the
    keyboard. Re-pin the live picker while the keyboard animation settles and
    whenever Filament replaces the filtered result DOM.
--}}
<style>
@media (max-width: 768px) {
    body.vx-pallet-screen [data-vx-ios-picker="1"] {
        position: fixed !important;
        left: 10px !important;
        right: 10px !important;
        bottom: auto !important;
        width: auto !important;
        min-width: 0 !important;
        max-width: none !important;
        height: auto !important;
        transform: none !important;
        z-index: 2147483000 !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
        border-radius: 14px !important;
    }

    body.vx-pallet-screen [data-vx-ios-picker="1"] [role="listbox"] {
        position: static !important;
        flex: 1 1 auto !important;
        min-height: 0 !important;
        max-height: none !important;
        overflow-y: auto !important;
        overscroll-behavior: contain !important;
        -webkit-overflow-scrolling: touch !important;
    }
}
</style>

<script>
(() => {
    if (window.__vxIosInventoryPickerFixLoaded) return;
    window.__vxIosInventoryPickerFixLoaded = true;

    const isMobile = () => window.innerWidth <= 768;
    const isPalletWorkflow = () => {
        const path = location.pathname.toLowerCase();
        return path.includes('/admin/pallet')
            || path.includes('/admin/receive-inventory')
            || !!document.querySelector('input[placeholder*="Search or browse inventory" i]');
    };
    const visible = (el) => !!el && el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';

    const pickerFor = (input) => {
        if (!input) return null;
        let node = input;
        for (let i = 0; node && node !== document.body && i < 14; i++, node = node.parentElement) {
            const listbox = node.querySelector?.('[role="listbox"]');
            if (listbox && visible(listbox)) return node;
        }
        return null;
    };

    const currentPicker = () => {
        let picker = pickerFor(document.activeElement);
        if (picker) return picker;

        const input = [...document.querySelectorAll('input[type="search"], input[role="combobox"]')]
            .find(el => visible(el) && pickerFor(el));
        return pickerFor(input);
    };

    const pin = () => {
        if (!isMobile() || !isPalletWorkflow()) return;

        const picker = currentPicker();
        if (!picker) return;

        document.body.classList.add('vx-pallet-screen');
        document.querySelectorAll('[data-vx-ios-picker="1"]').forEach(el => {
            if (el !== picker) delete el.dataset.vxIosPicker;
        });
        picker.dataset.vxIosPicker = '1';

        const vv = window.visualViewport;
        const viewportTop = vv ? vv.offsetTop : 0;
        const viewportHeight = vv ? vv.height : window.innerHeight;

        // Keep the whole picker inside the *visible* viewport, not the layout
        // viewport that extends behind the iOS keyboard.
        const top = Math.round(viewportTop + 10);
        const available = Math.max(150, Math.round(viewportHeight - 20));
        const height = Math.min(430, available);

        picker.style.setProperty('position', 'fixed', 'important');
        picker.style.setProperty('left', '10px', 'important');
        picker.style.setProperty('right', '10px', 'important');
        picker.style.setProperty('top', `${top}px`, 'important');
        picker.style.setProperty('bottom', 'auto', 'important');
        picker.style.setProperty('width', 'auto', 'important');
        picker.style.setProperty('height', 'auto', 'important');
        picker.style.setProperty('max-height', `${height}px`, 'important');
        picker.style.setProperty('transform', 'none', 'important');
        picker.style.setProperty('z-index', '2147483000', 'important');

        const listbox = picker.querySelector('[role="listbox"]');
        if (listbox) {
            // Reserve room for the search row/helper text. This prevents the
            // results themselves from continuing underneath the keyboard.
            const chromeHeight = Math.max(72, picker.scrollHeight - listbox.scrollHeight);
            const resultHeight = Math.max(96, height - chromeHeight);
            listbox.style.setProperty('max-height', `${resultHeight}px`, 'important');
            listbox.style.setProperty('overflow-y', 'auto', 'important');
        }
    };

    let settleTimers = [];
    const settle = () => {
        settleTimers.forEach(clearTimeout);
        settleTimers = [];

        // iOS keyboard/visualViewport animation is asynchronous. Cover both
        // the first frame and the later viewport resize instead of requiring
        // the user to close/re-open the keyboard.
        [0, 40, 90, 160, 260, 400, 650, 900, 1200].forEach(delay => {
            settleTimers.push(setTimeout(() => requestAnimationFrame(pin), delay));
        });
    };

    document.addEventListener('focusin', event => {
        if (event.target?.matches?.('input[type="search"], input[role="combobox"]')) settle();
    }, true);

    document.addEventListener('input', event => {
        if (event.target?.matches?.('input[type="search"], input[role="combobox"]')) settle();
    }, true);

    document.addEventListener('livewire:navigated', settle);
    window.visualViewport?.addEventListener('resize', settle);
    window.visualViewport?.addEventListener('scroll', settle);
    window.addEventListener('orientationchange', settle);

    new MutationObserver(() => {
        if (document.activeElement?.matches?.('input[type="search"], input[role="combobox"]')) {
            requestAnimationFrame(pin);
        }
    }).observe(document.body, { childList: true, subtree: true });

    settle();
})();
</script>
