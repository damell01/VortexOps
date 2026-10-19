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
    window.Livewire.hook('request', ({ payload, respond, succeed, fail }) => {
        // Livewire 4 hands over the request body as a JSON string, not an object;
        // reading it as an object found no calls, so none of this ever ran.
        let body = payload;
        if (typeof body === 'string') { try { body = JSON.parse(body); } catch (_) { body = null; } }
        const actions = (body?.components || []).some(component => (component.calls || []).some(call => !['$refresh', '$set', '$dispatch', '__dispatch'].includes(call.method)));
        if (!actions) return;
        pending++;
        let finished = false;
        // Only for something slow: a quick save flashing "Working…" is just noise.
        const slow = setTimeout(() => { if (!finished) renderFeedback('Working…'); }, 700);
        const finish = () => {
            if (finished) return;
            finished = true; clearTimeout(slow); pending = Math.max(0, pending - 1);
            if (pending === 0 && !notice?.classList.contains('is-error')) { notice?.remove(); notice = null; }
        };
        succeed(finish);
        // Livewire 4 only calls fail() when the network drops. A server error
        // (500) still "responds", so a save that blew up on the server showed
        // nothing at all and looked like a dead button.
        let reported = false;
        respond(({ status }) => {
            if (status < 400) return;
            reported = true;
            finish();
            renderFeedback(status === 419 ? 'Your session expired. Refresh the page and sign in again.'
                : (status === 403 ? 'You don\u2019t have permission to do that.' : 'That didn\u2019t save \u2014 something went wrong on our side. Try again, and tell us if it keeps happening.'), true);
        });
        fail(({ status }) => {
            if (reported) return; // the server's own answer above says it better
            finish();
            renderFeedback(status === 419 ? 'Your session expired. Refresh the page and sign in again.' : 'The action could not be confirmed. Check the record before retrying.', true);
        });
    });
}
document.addEventListener('livewire:init', installFeedback);
installFeedback();
document.addEventListener('livewire:navigating', () => { pending = 0; notice?.remove(); notice = null; });
