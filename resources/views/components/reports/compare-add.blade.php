@props(['kind', 'id', 'name', 'small' => false])
{{-- "+ Compare": adds a streamer, channel, show or format to the compare tray. --}}
<button type="button" x-data
    {{ $attributes->class(['vxrs-add', 'is-sm' => $small]) }}
    x-on:click.stop.prevent="$store.vxcmp && $store.vxcmp.toggle(@js($kind), @js((string) $id), @js($name))"
    :class="{ 'is-in': $store.vxcmp && $store.vxcmp.has(@js($kind), @js((string) $id)) }"
    :aria-pressed="$store.vxcmp && $store.vxcmp.has(@js($kind), @js((string) $id)) ? 'true' : 'false'"
    title="Add {{ $name }} to a comparison">
    <span x-text="$store.vxcmp && $store.vxcmp.has(@js($kind), @js((string) $id)) ? @js($small ? '✓' : '✓ Comparing') : @js($small ? '+' : '+ Compare')">{{ $small ? '+' : '+ Compare' }}</span>
</button>
