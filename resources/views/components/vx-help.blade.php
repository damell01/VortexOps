@props(['topic'])
@php $help = \App\Support\HelpTopics::get($topic); @endphp
@if($help)
<button type="button" class="vx-help-btn" data-vx-help="{{ $topic }}" aria-label="How this works: {{ $help['title'] }}" title="How this works"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="8.25" /><path d="M7.9 7.6a2.2 2.2 0 0 1 4.25.75c0 1.45-2.15 1.9-2.15 3.15" /><circle cx="10" cy="14.3" r=".6" class="vx-help-dot" /></svg></button>
@endif
