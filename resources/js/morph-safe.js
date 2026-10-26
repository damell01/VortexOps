// Keep our own additions out of Livewire's way.
//
// Several scripts add elements to the page after it loads: the styled
// dropdown buttons, phone pagers, carousel dots, empty-state Pokémon. The
// server's HTML has none of them, and Livewire matches old and new elements
// by position. One extra element near the top of a component made every
// element after it look different, so Livewire rebuilt them all on each
// update — which replaced whatever input had focus. Typing in a search box
// lost focus after the first results came back, and every update did far
// more work than it needed to.
//
// Elements marked data-vx-injected are taken out just before Livewire
// compares, so its view of the page lines up with the server's again. Their
// scripts put them back on the next pass, as they already do after any change.
let hooked = false;
function hook() {
    if (hooked || ! window.Livewire?.hook) return;
    hooked = true;
    window.Livewire.hook('morph', ({ el }) => {
        el.querySelectorAll('[data-vx-injected]').forEach((node) => node.remove());
    });
}
// This module can load before or after Livewire starts.
hook();
document.addEventListener('livewire:init', hook);

export function markInjected(node) {
    node.setAttribute('data-vx-injected', '');
    return node;
}
