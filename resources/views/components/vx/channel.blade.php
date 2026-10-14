@props(['name'])
{{-- A channel name with its Vortex accent dot. --}}
<span {{ $attributes->class('vx-ch') }} style="--vx-ch: {{ \App\Support\ChannelColor::for($name) }}">{{ $name }}</span>
