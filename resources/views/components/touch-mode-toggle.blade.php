{{-- "Large controls" switch for warehouse screens (scanning, receiving).
     Remembered on this device only; resources/js/app-shell.js re-applies it
     on every page load, and resources/css/app-shell.css does the sizing for
     anything inside [data-vx-touch-page]. --}}
<div class="flex justify-end" x-data="{ on: document.documentElement.classList.contains('vx-touch') }">
    <button type="button"
        x-on:click="on = !on; document.documentElement.classList.toggle('vx-touch', on); try { localStorage.setItem('vx-touch-mode', on ? '1' : '0') } catch (e) {}"
        :aria-pressed="on.toString()"
        class="vx-touch-toggle inline-flex min-h-10 items-center gap-2 rounded-xl border px-3 text-sm font-semibold"
        :class="on ? 'border-primary-500 bg-primary-600 text-white' : 'border-gray-300 bg-white text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200'">
        <x-heroicon-o-hand-raised class="h-5 w-5" />
        <span x-text="on ? 'Large controls: on' : 'Large controls'">Large controls</span>
    </button>
</div>
