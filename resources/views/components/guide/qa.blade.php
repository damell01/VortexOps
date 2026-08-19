@props(['items' => []])

{{-- Keyed by the symptom in the reader's own words, not by error code —
     someone arrives here with a sentence, not a stack trace. --}}
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
    @foreach($items as [$symptom, $answer])
        <div class="px-6 py-4">
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">&ldquo;{{ $symptom }}&rdquo;</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $answer }}</p>
        </div>
    @endforeach
</div>
