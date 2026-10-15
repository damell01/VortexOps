@props(['id'])
{{-- A one-line Pokédex tip from Rotom where people tend to get stuck. Dismissed per tip, per browser. --}}
<div {{ $attributes->class('vx-tip') }} role="note"
     x-data="{ shut: (() => { try { return localStorage.getItem('vx-tip-{{ $id }}') === '1' } catch (e) { return false } })() }"
     x-show="! shut" x-cloak>
    <img class="vx-tip-mon" src="/img/pokemon/rotom.gif" alt="" aria-hidden="true">
    <div class="vx-tip-bubble"><b>Pokédex tip</b> {{ $slot }}</div>
    <button type="button" class="vx-tip-x" aria-label="Hide this tip" title="Hide this tip"
            @click="shut = true; try { localStorage.setItem('vx-tip-{{ $id }}', '1') } catch (e) {}">×</button>
</div>
