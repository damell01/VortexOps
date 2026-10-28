@if (count($changes) || count($comingSoon))
<div role="dialog" aria-modal="true" aria-label="Inventory price updates" style="position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.85);display:grid;place-items:center;padding:16px">
<section style="background:#171717;color:white;padding:24px;border-radius:16px;max-width:560px;width:100%;max-height:85vh;overflow:auto">
<h2>Inventory updates</h2>
<p>Changes since your last review, including products on pallets awaiting receipt.</p>
@if (count($changes)) <h3>Price changes</h3> @endif
@foreach ($changes as $change)
<div wire:key="change-{{ $change['id'] }}" style="padding:12px;margin:8px 0;border:1px solid {{ $change['decreased'] ? '#f87171' : '#555' }};border-radius:8px">
<strong>{{ $change['name'] }}</strong>
<p>{{ $change['old_price'] ?? 'Unset' }} → {{ $change['new_price'] ?? 'Unset' }}</p>
@if ($change['decreased']) <strong>Price decreased</strong> @endif
</div>
@endforeach
@if (count($comingSoon))
<h3>Coming soon — awaiting pallet receipt</h3>
@foreach ($comingSoon as $item)
<div wire:key="pallet-line-{{ $item['id'] }}" style="display:flex;align-items:center;gap:12px;padding:12px;margin:8px 0;border:1px solid #555;border-radius:8px">
@if ($item['image']) <img src="{{ $item['image'] }}" alt="" style="width:56px;height:56px;object-fit:contain"> @endif
<div><strong>{{ $item['name'] }}</strong><p>{{ $item['pallet'] }} · {{ $item['cases'] }} cases expected</p>
@if ($item['expected']) <small>Expected {{ $item['expected'] }}</small> @endif</div>
</div>
@endforeach
@endif
<button wire:click="acknowledge" wire:loading.attr="disabled" style="background:#fbbf24;color:black;padding:12px;border-radius:8px;width:100%">I acknowledge these changes</button>
</section>
</div>
@endif
