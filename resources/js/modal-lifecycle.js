// Make sure closing a modal actually gives the page back.
//
// The reported symptom: open an action, cancel it, try the action again, and
// sometimes nothing responds — the whole screen behaves as though it is still
// behind a dialog.
//
// Filament opens modals with `x-trap.noscroll="isOpen"`, which does two things
// beyond drawing a box: it locks page scrolling, and it installs a focus trap
// that deliberately swallows focus and clicks outside the dialog. Both are
// released by Alpine when `isOpen` goes false on an element that is still in
// the document. When a modal is torn out of the DOM first — a Livewire morph
// swapping the subtree, a redirect landing mid-transition — that release never
// runs, and what is left is a page with no visible dialog on it that is still
// scroll-locked and still refusing clicks. Which is exactly what "frozen"
// looks like.
//
// This is a safety net at the symptom, not a rewrite of Filament's modal.
// It asks one question — is anything actually open? — and if the answer is no,
// it undoes the things an open modal is allowed to leave behind. When the
// normal path works it finds nothing to do and costs nothing.

const OPEN_MODAL = '.fi-modal.fi-modal-open, .fi-modal-window';

/**
 * Is a dialog genuinely on screen?
 *
 * Filament keeps closed modals mounted, so presence in the DOM proves nothing
 * — offsetParent does. A modal mid-open-transition has a parent already, so
 * this errs toward "open" and the net simply waits for the next check.
 */
function aModalIsOpen() {
    return [...document.querySelectorAll(OPEN_MODAL)].some(
        (el) => el.offsetParent !== null || el.getClientRects().length > 0,
    );
}

/**
 * Give back scrolling.
 *
 * Only inline styles are touched. The lock is applied inline, so anything set
 * in the stylesheet is somebody's deliberate rule and not ours to clear.
 */
function releaseScrollLock() {
    [document.documentElement, document.body].forEach((el) => {
        if (el.style.overflow === 'hidden') el.style.removeProperty('overflow');
        if (el.style.overflowY === 'hidden') el.style.removeProperty('overflow-y');

        // The lock pads for the scrollbar it just removed; left behind on its
        // own it shows as the page sitting a few pixels off centre.
        if (el.style.paddingRight) el.style.removeProperty('padding-right');
    });
}

/**
 * Remove overlays with nothing inside them.
 *
 * A backdrop left behind is invisible and full-screen, so every click lands on
 * it instead of the page. Only containers holding no dialog at all are removed
 * — an open modal's own backdrop has one.
 */
function removeOrphanedOverlays() {
    document.querySelectorAll('.fi-modal-window-ctn').forEach((ctn) => {
        if (ctn.querySelector('.fi-modal-window')) return;

        ctn.remove();
    });
}

/**
 * Release the focus trap that outlived its dialog.
 *
 * Alpine's trap listens on the document, so removing the element it guarded
 * does not stop it: focus keeps being pulled back to a node that is no longer
 * rendered, and clicks elsewhere are cancelled. Escape is the one signal the
 * trap itself acts on, so sending it is how the release is asked for through
 * the normal path rather than by reaching inside Alpine's internals.
 */
function releaseStrayFocusTrap() {
    const active = document.activeElement;

    if (active && active !== document.body && ! document.contains(active)) {
        document.body.focus?.();
    }

    document.dispatchEvent(new KeyboardEvent('keydown', {
        key: 'Escape', code: 'Escape', keyCode: 27, bubbles: true,
    }));
}

let strayTrapCleared = false;

function reconcile() {
    if (aModalIsOpen()) {
        strayTrapCleared = false;

        return;
    }

    releaseScrollLock();
    removeOrphanedOverlays();

    // Once per closed period. Sending Escape on a loop would cancel things
    // people are legitimately doing — a search field, an open select.
    if (! strayTrapCleared) {
        strayTrapCleared = true;
        releaseStrayFocusTrap();
    }
}

// Run after the frame the change landed in, so a modal that is opening is
// measured at its final size rather than mid-transition.
let queued = false;

function scheduleReconcile() {
    if (queued) return;

    queued = true;
    requestAnimationFrame(() => {
        queued = false;
        reconcile();
    });
}

const observer = new MutationObserver(scheduleReconcile);

observer.observe(document.body, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['class', 'style'],
});

// A Livewire round trip is the moment a morph can drop a modal without ever
// setting isOpen false, which is the case the observer alone can miss.
document.addEventListener('livewire:navigated', scheduleReconcile);
document.addEventListener('livewire:init', () => {
    window.Livewire?.hook?.('morph.updated', scheduleReconcile);
    window.Livewire?.hook?.('commit', ({ respond }) => respond(scheduleReconcile));
});

scheduleReconcile();

export { aModalIsOpen, releaseScrollLock, removeOrphanedOverlays, reconcile };
