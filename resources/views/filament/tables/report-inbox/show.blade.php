@php
    $record = $getRecord();
    $show = $record->show;
    $title = $show?->title ?: 'Report #' . $record->id;
    $name = $record->streamer?->name ?: 'No streamer';
    $initials = collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
@endphp
<div class="vxi-show">
    @if ($show?->cover_image_url)
        <img src="{{ $show->cover_image_url }}" alt="" class="vxi-thumb" loading="lazy" referrerpolicy="no-referrer">
    @else
        <span class="vxi-thumb vxi-thumb-blank" aria-hidden="true">{{ mb_strtoupper(mb_substr($title, 0, 1)) }}</span>
    @endif
    <div class="min-w-0">
        <div class="vxi-title" title="{{ $title }}">{{ $title }}</div>
        <div class="vxi-meta">{{ $show?->show_date?->format('M j, Y') ?? 'No date' }}@if ($show?->channel?->name) · {{ $show->channel->name }}@endif</div>
        <div class="vxi-who"><span class="vxi-initials" aria-hidden="true">{{ $initials ?: '?' }}</span>{{ $name }}</div>
    </div>
</div>
