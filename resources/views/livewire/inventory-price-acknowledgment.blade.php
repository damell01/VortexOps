@if (count($changes))
<div role="dialog" aria-modal="true" aria-label="Inventory price updates" style="position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.85);display:grid;place-items:center;padding:16px">
<section style="background:#171717;color:white;padding:24px;border-radius:16px;max-width:560px;width:100%;max-height:85vh;overflow:auto">
<h2>Inventory price changes</h2>
<p>Review the updated selling prices before continuing.</p>
@foreach ($changes as $change)
<div wire:key="change-{{ $change['id'] }}" style="padding:12px;margin:8px 0;border:1px solid {{ $change['decreased'] ? '#f87171' : '#555' }};border-radius:8px">
<strong>{{ $change['name'] }}</strong>
<p>{{ $change['old_price'] ?? 'Unset' }} → {{ $change['new_price'] ?? 'Unset' }}</p>
@if ($change['decreased']) <strong>Price decreased</strong> @endif
</div>
@endforeach
<button wire:click="acknowledge" wire:loading.attr="disabled" style="background:#fbbf24;color:black;padding:12px;border-radius:8px;width:100%">I acknowledge these changes</button>
</section>
</div>
@endif
