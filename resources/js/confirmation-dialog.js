import '../css/confirmation-dialog.css';

let active = null;

// Resolve only after an explicit choice; navigation always cancels pending work.
window.vxConfirm = function (message, { expected = null } = {}) {
    if (active) return Promise.resolve(false);
    const previousFocus = document.activeElement;
    const dialog = document.createElement('dialog');
    dialog.className = 'vx-confirm-dialog';
    dialog.setAttribute('aria-labelledby', 'vx-confirm-title');
    dialog.setAttribute('aria-describedby', 'vx-confirm-message');
    dialog.innerHTML = `<form method="dialog"><h2 id="vx-confirm-title">Confirm action</h2><p id="vx-confirm-message"></p><label class="vx-confirm-input" hidden><span></span><input type="text" autocomplete="off"></label><div class="vx-confirm-actions"><button type="button" data-cancel>Cancel</button><button type="submit" data-confirm>Confirm</button></div></form>`;
    dialog.querySelector('p').textContent = message || 'Are you sure?';
    const input = dialog.querySelector('input');
    const confirm = dialog.querySelector('[data-confirm]');
    if (expected !== null) {
        dialog.querySelector('label').hidden = false;
        dialog.querySelector('label span').textContent = `Type ${expected} to confirm`;
        confirm.disabled = true;
        input.addEventListener('input', () => { confirm.disabled = input.value !== expected; });
    }
    return new Promise(resolve => {
        let settled = false;
        const finish = accepted => {
            if (settled) return;
            settled = true;
            active = null;
            if (dialog.open) dialog.close();
            dialog.remove();
            if (previousFocus?.isConnected) previousFocus.focus({ preventScroll: true });
            resolve(accepted);
        };
        active = { finish };
        dialog.querySelector('form').addEventListener('submit', event => {
            event.preventDefault();
            if (expected === null || input.value === expected) finish(true);
        });
        dialog.querySelector('[data-cancel]').addEventListener('click', () => finish(false));
        dialog.addEventListener('cancel', event => { event.preventDefault(); finish(false); });
        dialog.addEventListener('close', () => finish(false));
        document.body.append(dialog);
        try {
            dialog.showModal();
            (expected === null ? dialog.querySelector('[data-cancel]') : input).focus();
        } catch (_) { finish(false); }
    });
};

const waiting = new WeakSet();
function prepareConfirmation(element) {
    const attribute = [...element.attributes].find(attr => attr.name === 'wire:confirm' || attr.name.startsWith('wire:confirm.'));
    if (!attribute) return;
    // Livewire calls this continuation before executing the original action.
    // Install during capture, before Livewire's click/submit handlers run.
    element.__livewire_confirm = async (action, instead = () => {}) => {
        if (waiting.has(element)) { instead(); return; }
        waiting.add(element);
        try {
            let message = attribute.value.replaceAll('\\n', '\n');
            let expected = null;
            if (attribute.name.split('.').includes('prompt')) {
                [message, expected] = message.split('|');
                if (!expected) { instead(); return; }
            }
            const accepted = await window.vxConfirm(message, { expected });
            if (accepted && element.isConnected) action();
            else instead();
        } finally { waiting.delete(element); }
    };
}
for (const eventName of ['click', 'submit']) {
    document.addEventListener(eventName, event => {
        for (const element of event.composedPath()) {
            if (element instanceof Element) prepareConfirmation(element);
        }
    }, true);
}
document.addEventListener('livewire:navigating', () => active?.finish(false));
window.addEventListener('pagehide', () => active?.finish(false));
