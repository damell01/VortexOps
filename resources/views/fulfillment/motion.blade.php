{{-- Small motion helpers shared by the Fulfillment pages. Respects reduced motion. --}}
<script>
    window.vxfCountUp = window.vxfCountUp || function (el) {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const text = el.textContent.trim();
        const m = text.match(/^(\d+)(.*)$/s);
        if (!m) return;
        const to = parseInt(m[1], 10), rest = m[2];
        if (!to) return;
        const start = performance.now(), dur = 800;
        const tick = (t) => {
            const k = Math.min(1, (t - start) / dur), e = 1 - Math.pow(1 - k, 3);
            el.textContent = Math.round(to * e) + rest;
            if (k < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    };
</script>
