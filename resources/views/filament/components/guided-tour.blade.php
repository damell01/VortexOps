{{--
    Boots the guided tour for this screen.

    The tour engine is ~6KB and only three or four screens have a tour, so it is
    imported on first use rather than bundled into every page — the same
    treatment the barcode scanner gets.

    A launcher is placed next to the page heading so the tour can be replayed
    after it has been dismissed; it is inserted rather than templated into the
    header because resource List and Edit pages render Filament's own views.
--}}
@once
    @vite('resources/css/guided-tour.css')
@endonce

<script type="application/json" id="vx-tour-data">@json($tour)</script>

<script>
(function () {
    const el = document.getElementById('vx-tour-data');
    if (! el) return;

    let tour;
    try {
        tour = JSON.parse(el.textContent);
    } catch {
        return;                                  // never break a page over a tour
    }

    let engine = null;

    async function ensureEngine() {
        if (! engine) {
            engine = await import({{ Illuminate\Support\Js::from(Vite::asset('resources/js/guided-tour.js')) }});
        }
        return engine;
    }

    async function run() {
        const { startTour } = await ensureEngine();

        startTour(tour, {
            onFinish() {
                // Recorded whether it was completed or skipped: someone who
                // dismissed a tour has decided about it, and reopening it on
                // their next visit ignores that decision.
                fetch(@js(route('tours.complete')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ tour: tour.id }),
                }).catch(() => {});
            },
        });
    }

    function addLauncher() {
        const heading = document.querySelector('.fi-header-heading, .fi-header h1');
        if (! heading || heading.parentElement.querySelector('.vx-tour-launch')) return;

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'vx-tour-launch';
        button.textContent = '? How this page works';
        button.addEventListener('click', run);
        heading.insertAdjacentElement('afterend', button);
    }

    function boot() {
        addLauncher();

        // Auto-open only for someone who has not seen this tour. Waiting a beat
        // lets the table and its toolbar render, so steps are not dropped for
        // pointing at elements that exist a moment later.
        if (tour.auto) setTimeout(run, 700);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
</script>
