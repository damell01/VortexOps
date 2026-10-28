// Navigation feedback never blocks the page or waits for a decorative animation.
let progress;
let cleanupTimer;
let navigation = 0;
const finish = () => {
    navigation++;
    clearTimeout(cleanupTimer);
    progress?.remove();
    progress = null;
};
document.addEventListener('livewire:navigate', event => {
    finish();
    const current = navigation;
    // Other handlers may cancel navigation later in the same event dispatch.
    queueMicrotask(() => {
        if (event.defaultPrevented || current !== navigation) return;
        progress = document.createElement('div');
        progress.id = 'vx-nav-bar';
        progress.className = 'vx-run';
        progress.setAttribute('role', 'progressbar');
        progress.setAttribute('aria-label', 'Loading page');
        document.body.append(progress);
        cleanupTimer = setTimeout(finish, 15000);
    });
});
document.addEventListener('livewire:navigated', finish);
window.addEventListener('pageshow', finish);
