/** Visual enhancements only. Filament owns validation, filters and modal traps.
 * Delegation survives SPA navigation and morphs without registering listeners
 * for every field on every update.
 */
class UIImprovements {
    init() {
        if (this.installed) return;
        this.installed = true;
        document.addEventListener('keydown', event => {
            if (event.key === 'Tab') document.body.classList.add('showing-focus-ring');
        });
        document.addEventListener('pointerdown', () => document.body.classList.remove('showing-focus-ring'));
        document.addEventListener('focusin', event => event.target.closest?.('.fi-input-wrp')?.classList.add('focused'));
        document.addEventListener('focusout', event => {
            const wrapper = event.target.closest?.('.fi-input-wrp');
            if (wrapper && !wrapper.contains(event.relatedTarget)) wrapper.classList.remove('focused');
        });
    }
    constructor() { this.init(); }
}
window.uiImprovements ??= new UIImprovements();
