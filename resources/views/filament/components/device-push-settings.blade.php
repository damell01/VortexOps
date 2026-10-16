<section class="card" wire:ignore data-vx-push data-config="{{ route('push.config') }}" data-status="{{ route('push.status') }}" data-store="{{ route('push.store') }}" data-destroy="{{ route('push.destroy') }}" data-test="{{ route('push.test') }}">
    <h2 class="text-base font-semibold">Push notifications</h2>
    <p class="sub" style="margin:8px 0">Receive alerts on this device, even when Vortex Ops isn’t open. Push follows your enabled in-app alerts. Details stay inside the app.</p>
    <p data-vx-push-message role="status" class="sub" style="margin:12px 0">Checking device support…</p>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button type="button" class="btn" data-vx-push-enable disabled>Enable on this device</button>
        <button type="button" class="btn" data-vx-push-disable hidden>Disable on this device</button>
        <button type="button" class="btn" data-vx-push-test hidden>Send test notification</button>
    </div>
</section>
<script src="{{ asset('js/device-push.js') }}?v=1" defer></script>
