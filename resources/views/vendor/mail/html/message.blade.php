{{-- Every email: brand header with the logo, the message, then a footer saying why it came and where to change that. --}}
@php($brand = \App\Support\MailBrand::name())
@php($logo = \App\Support\MailBrand::logoUrl())
<x-mail::layout>
<x-slot:header>
<tr>
<td class="header">
<a href="{{ url('/admin') }}" class="brand" style="display:inline-block;text-decoration:none;">
@if($logo)<img src="{{ $logo }}" class="logo" width="44" height="44" alt="{{ $brand }}">@endif
<span class="brand-name">{{ $brand }}</span>
</a>
</td>
</tr>
</x-slot:header>

{!! $slot !!}

@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

<x-slot:footer>
<x-mail::footer>
You're receiving this from {{ $brand }} operations.<br>
[Manage your notifications]({{ \App\Support\MailBrand::preferencesUrl() }}) · © {{ date('Y') }} {{ $brand }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
