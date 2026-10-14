@php
    $vxImpersonator = \App\Support\Impersonation::impersonator();
    $vxViewing = auth()->user();
@endphp
<div id="vx-impersonation-banner" role="status"
     style="position:sticky;top:0;z-index:1100;display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:.5rem 1rem;padding:.55rem 1rem;background:#b45309;color:#fff;font-size:.875rem;font-weight:600;box-shadow:0 1px 3px rgba(0,0,0,.25);">
    <span style="display:inline-flex;align-items:center;gap:.4rem;">
        <x-filament::icon icon="heroicon-o-eye" style="width:1.1rem;height:1.1rem;" />
        You're logged in as {{ $vxViewing?->name }}
        <span style="font-weight:400;opacity:.85;">({{ $vxViewing?->email }})</span>
    </span>
    <form method="POST" action="{{ route('impersonation.stop') }}" style="margin:0;">
        @csrf
        <button type="submit"
                style="background:#fff;color:#92400e;border:0;border-radius:.375rem;padding:.3rem .75rem;font-weight:700;font-size:.8125rem;cursor:pointer;">
            Return to {{ $vxImpersonator?->name ?? 'your account' }}
        </button>
    </form>
</div>
