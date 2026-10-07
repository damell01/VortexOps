@props(['url', 'color' => 'primary', 'align' => 'center'])
@php($bg = match ($color) { 'error', 'red' => '#dc2626', 'success', 'green' => '#16a34a', default => \App\Support\MailBrand::color() })
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
<a href="{{ $url }}" class="button" target="_blank" rel="noopener" style="background-color:{{ $bg }};border-color:{{ $bg }};border-top:12px solid {{ $bg }};border-bottom:12px solid {{ $bg }};border-left:22px solid {{ $bg }};border-right:22px solid {{ $bg }};">{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
