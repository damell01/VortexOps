<x-filament-panels::page>
@php
    $record = $this->record;
    $show = $record->show;
    $items = $record->items()->with(['inventoryItem','location'])->get();
    $isAdmin = (bool) auth()->user()?->isAdmin();
    $locked = \App\Filament\Resources\StreamerLogResource::isLockedForCurrentUser($record);
@endphp
<div class="mx-auto w-full max-w-6xl space-y-5" data-vx-page="report-review" x-data="{ tab: 'report' }">
    <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0"><p class="text-xs font-bold uppercase tracking-wide text-primary-600">{{ $isAdmin ? 'Report review' : 'Your show report' }}</p><h2 class="mt-1 break-words text-xl font-bold">{{ $show?->title ?: 'Report #' . $record->id }}</h2><p class="mt-2 text-sm text-gray-500">{{ $record->streamer?->name ?: 'No streamer linked' }} · {{ $show?->show_date?->format('M j, Y') ?: 'Show date unavailable' }} · {{ \App\Models\StreamerLogEntry::statusLabels()[$record->status] ?? $record->status }}</p></div>
            <div class="flex flex-wrap gap-2"><a href="{{ \App\Filament\Resources\StreamerLogResource::getUrl('index') }}" class="inline-flex min-h-11 items-center rounded-xl border border-gray-300 px-3 text-sm font-semibold dark:border-gray-600">← Report inbox</a>@if($show)<a href="{{ \App\Filament\Resources\ShowResource::getUrl('view',['record'=>$show]) }}" class="inline-flex min-h-11 items-center rounded-xl border border-gray-300 px-3 text-sm font-semibold dark:border-gray-600">Open show</a>@endif</div>
        </div>
        @if(!$show)<p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">This legacy report is not linked to a show. Its saved details are shown below; show-specific inventory and fulfillment actions need a linked show.</p>@endif
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach(['Item lines'=>$items->count(),'Units'=>$items->sum('quantity'),'Hours'=>number_format((float)$record->hours_streamed,2),'Reported product cost'=>'$' . number_format((float)$record->product_cost,2)] as $label=>$value)<div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800"><p class="text-xs text-gray-500">{{ $label }}</p><p class="mt-1 text-xl font-bold">{{ $value }}</p></div>@endforeach
        </div>
    </section>
    @if($record->hasPendingRevisionRequest())
        <section class="rounded-2xl border border-amber-300 bg-amber-50 p-5 dark:border-amber-800 dark:bg-amber-950/20"><h3 class="font-bold">Streamer asked to reopen this report</h3><p class="mt-2 whitespace-pre-line text-sm">{{ $record->revision_reason ?: 'No reason provided.' }}</p><p class="mt-2 text-xs text-gray-500">Requested {{ $record->revision_requested_at?->diffForHumans() }}. {{ $isAdmin ? 'Use Approve Edit Request above to reopen it, or Decline Edit Request to respond with a reason.' : 'An admin will review the request. The report stays locked until reopened.' }}</p></section>
    @endif
    @if($record->approval_notes)<section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900"><h3 class="font-bold">Review notes</h3><p class="mt-2 whitespace-pre-line text-sm">{{ $record->approval_notes }}</p></section>@endif
    <nav class="flex gap-2" aria-label="Report sections"><button type="button" @click="tab='report'" :aria-pressed="tab==='report'" class="min-h-11 rounded-xl border border-gray-300 px-4 text-sm font-semibold dark:border-gray-600">Items & notes</button><button type="button" @click="tab='details'" :aria-pressed="tab==='details'" class="min-h-11 rounded-xl border border-gray-300 px-4 text-sm font-semibold dark:border-gray-600">{{ $locked ? 'Saved details' : 'Edit details' }}</button></nav>
    <section x-show="tab==='report'" class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-wrap items-center justify-between gap-3"><h3 class="font-bold">Items reported by the streamer</h3>@if($show && !$locked && \App\Filament\Pages\EndOfStreamForm::canAccess())<a href="{{ \App\Filament\Pages\EndOfStreamForm::getUrl(['showId'=>$show->id],panel:'admin') }}" class="inline-flex min-h-11 items-center rounded-xl bg-primary-600 px-4 text-sm font-semibold text-white">Open item editor</a>@endif</div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            @forelse($items as $item)<article class="rounded-xl border border-gray-200 p-4 dark:border-gray-700"><h4 class="font-semibold">{{ $item->item_name ?: $item->inventoryItem?->name ?: 'Unlisted item' }}</h4><p class="mt-1 text-xs text-gray-500">{{ \App\Models\StreamerLogItem::DISPOSITIONS[$item->disposition] ?? $item->disposition }} · {{ $item->location?->name ?: 'No location' }}</p><div class="mt-3 flex flex-wrap justify-between gap-2 text-sm"><span>Quantity <strong>{{ $item->quantity }}</strong></span><span>Unit cost <strong>{{ $item->unit_cost === null ? 'Missing' : '$' . number_format((float)$item->unit_cost,2) }}</strong></span></div>@if(!$item->inventory_item_id)<p class="mt-2 text-xs font-semibold text-amber-700 dark:text-amber-300">Needs inventory matching in the show workspace</p>@endif @if($item->notes)<p class="mt-2 whitespace-pre-line text-sm text-gray-500">{{ $item->notes }}</p>@endif</article>@empty<p class="text-sm text-gray-500">No streamer-reported item lines are saved on this report.</p>@endforelse
        </div>
        <div class="mt-5 border-t border-gray-200 pt-4 dark:border-gray-700"><h3 class="font-bold">Show notes</h3><p class="mt-2 whitespace-pre-line text-sm">{{ $record->notes ?: 'No show notes provided.' }}</p></div>
    </section>
    <section x-show="tab==='details'" x-cloak class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
        @if($locked)<p class="mb-4 text-sm text-gray-500">This report is locked. Request reopening before making changes.</p>@endif
        <form wire:submit="save" class="space-y-5">{{ $this->form }}@if(!$locked)<div class="flex flex-wrap gap-3">@foreach($this->getFormActions() as $action){{ $action }}@endforeach</div>@endif</form>
    </section>
</div>
</x-filament-panels::page>
