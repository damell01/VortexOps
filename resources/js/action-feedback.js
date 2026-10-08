import '../css/action-feedback.css';
let installed = false;
let pending = 0;
let notice = null;
function renderFeedback(text, error = false) {
    notice?.remove();
    notice = document.createElement('div');
    notice.className = 'vx-action-feedback' + (error ? ' is-error' : '');
    notice.setAttribute('role', error ? 'alert' : 'status');
    const message = document.createElement('span');
    message.textContent = text;
    notice.append(message);
    if (error) {
        const close = document.createElement('button');
        close.type = 'button'; close.textContent = 'Dismiss';
        close.addEventListener('click', () => { notice?.remove(); notice = null; });
        notice.append(close);
    }
    document.body.append(notice);
}
function installFeedback() {
    if (installed || !window.Livewire) return;
    installed = true;
    window.Livewire.hook('request', ({ payload, succeed, fail }) => {
        const actions = (payload?.components || []).some(component => (component.calls || []).some(call => !['$refresh', '$set', '$dispatch', '__dispatch'].includes(call.method)));
        if (!actions) return;
        pending++;
        renderFeedback('Working…');
        let finished = false;
        const finish = () => {
            if (finished) return;
            finished = true; pending = Math.max(0, pending - 1);
            if (pending === 0 && !notice?.classList.contains('is-error')) { notice?.remove(); notice = null; }
        };
        succeed(finish);
        fail(({ status }) => {
            finish();
            renderFeedback(status === 419 ? 'Your session expired. Refresh the page and sign in again.' : 'The action could not be confirmed. Check the record before retrying.', true);
        });
    });
}
document.addEventListener('livewire:init', installFeedback);
installFeedback();
document.addEventListener('livewire:navigating', () => { pending = 0; notice?.remove(); notice = null; });
