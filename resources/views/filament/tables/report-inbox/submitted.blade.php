@php $at = $getRecord()->submitted_at; @endphp
<div class="vxi-submitted">
    @if ($at)
        <div class="vxi-when">{{ $at->diffForHumans() }}</div>
        <div class="vxi-meta">{{ $at->timezone(\App\Support\BusinessTime::zone())->format('M j, g:i A') }}</div>
    @else
        <div class="vxi-meta">Not submitted</div>
    @endif
</div>
