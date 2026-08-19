// Bring a modal into view when it opens.
//
// Row actions open their modal wherever the page happens to be scrolled, and
// when the row is low on a long table the dialog lands with its heading above
// the viewport. Body scroll is locked while a modal is open, so there is no way
// to scroll up to the part you cannot see — the buttons are reachable and the
// question they answer is not, which is the worst possible half.
//
// Filament renders modals through Livewire, so they appear in the DOM after the
// click rather than being present and hidden. That makes this a job for an
// observer rather than a click handler.

const MODAL = '.fi-modal-window';

function bringIntoView(modal) {
    // Two frames: one for Filament's open transition to set its final size, one
    // for layout to settle at that size. Measuring earlier scrolls to where the
    // dialog was mid-animation.
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            const rect = modal.getBoundingClientRect();

            // Already fully visible — leave it alone rather than nudging the
            // page under someone who is reading it.
            if (rect.top >= 0 && rect.bottom <= window.innerHeight) return;

            modal.scrollIntoView({
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                block: rect.height > window.innerHeight ? 'start' : 'center',
                inline: 'nearest',
            });
        });
    });
}

const seen = new WeakSet();

function check(node) {
    if (! (node instanceof HTMLElement)) return;

    const modals = node.matches?.(MODAL) ? [node] : node.querySelectorAll?.(MODAL) ?? [];

    modals.forEach((modal) => {
        // A modal that closes and reopens is a new element, so WeakSet identity
        // is the right test — it re-centres on reopen without fighting a modal
        // that merely re-rendered.
        if (seen.has(modal)) return;
        seen.add(modal);
        bringIntoView(modal);
    });
}

const observer = new MutationObserver((records) => {
    records.forEach((record) => record.addedNodes.forEach(check));
});

observer.observe(document.body, { childList: true, subtree: true });

// Catch any modal already present when this module finishes loading.
document.querySelectorAll(MODAL).forEach(check);
