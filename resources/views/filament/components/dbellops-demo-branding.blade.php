{{-- Demo-only visual branding for the real VortexOps interface. Never loaded in production. --}}
<style>
:root {
  --vx-soft-primary: #ccfbf1 !important;
  --vx-primary-text: #0f766e !important;
  --vx-violet-bg: #ccfbf1 !important;
  --vx-violet: #0d9488 !important;
  --primary-500: 13 148 136;
  --primary-600: 13 148 136;
}
html body .fi-btn-color-primary, html body button[type="submit"], html body .fi-ac-btn-action { --tw-gradient-from: #0d9488; }
html body .fi-sidebar-item.fi-active > .fi-sidebar-item-btn { background: rgb(13 148 136 / .16) !important; }
html body .fi-sidebar-item.fi-active svg { color: #0d9488 !important; }
html body [style*="#7c3aed"] { filter: hue-rotate(80deg); }
</style>
<script>
(() => {
  const substitutions = [
    [/Vortex\\s*Ops/gi, 'DBellOps'],
    [/VortexOps/gi, 'DBellOps'],
    [/Vortex Breaks operations center/gi, 'DBellOps seller operations center']
  ];
  const replaceText = (value) => substitutions.reduce((text, [pattern, replacement]) => text.replace(pattern, replacement), value);
  const update = (root) => {
    if (!root || root.nodeType !== 1) return;
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    let node;
    while ((node = walker.nextNode())) {
      if (node.parentElement?.closest('script,style,textarea,code,pre')) continue;
      const replacement = replaceText(node.nodeValue);
      if (replacement !== node.nodeValue) node.nodeValue = replacement;
    }
    for (const element of [root, ...root.querySelectorAll('[title],[aria-label],[alt],[placeholder]')]) {
      for (const attribute of ['title','aria-label','alt','placeholder']) {
        if (element.hasAttribute?.(attribute)) {
          const old = element.getAttribute(attribute);
          const next = replaceText(old);
          if (next !== old) element.setAttribute(attribute, next);
        }
      }
    }
  };
  const start = () => {
    update(document.body);
    document.title = replaceText(document.title);
    const observer = new MutationObserver((records) => {
      for (const record of records) {
        if (record.type === 'characterData') {
          const node = record.target;
          if (node.parentElement?.closest('script,style,textarea,code,pre')) continue;
          const next = replaceText(node.nodeValue);
          if (next !== node.nodeValue) node.nodeValue = next;
        } else for (const node of record.addedNodes) {
          if (node.nodeType === 1) update(node);
        }
      }
      document.title = replaceText(document.title);
    });
    observer.observe(document.body, {childList:true,subtree:true,characterData:true});
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once:true});
  else start();
})();
</script>
