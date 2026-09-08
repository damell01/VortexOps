@php
    use App\Models\StreamerLogEntry;
    use App\Filament\Resources\StreamerLogResource;

    /** @var StreamerLogEntry $record */
    $record = $this->record;
    $show = $record->show;
    $user = auth()->user();
    $isStreamer = $user?->isStreamer() && !$user?->isAdmin();
    $isAdmin = $user?->isAdmin() || $user?->isOwner();
    $orders = $show?->orders()->with(['inventoryItem'])->whereNotNull('inventory_item_id')->get() ?? collect();
    $totalItems = $orders->count();
    $totalQuantity = (int)$orders->sum('quantity');
    $totalCost = (float)$orders->sum('total_cost');
    $canEdit = !$isStreamer || !StreamerLogResource::isLockedForCurrentUser($record);
    $approved = $record->approval_status === 'approved' || $record->status === 'admin_approved';
    $changesRequested = $record->approval_status === 'rejected' || $record->status === 'changes_requested';
    $submitted = $record->isSubmitted();
    $statusLabel = $approved ? 'Approved' : ($changesRequested ? 'Changes Requested' : ($submitted ? 'Awaiting Review' : 'Draft'));
    $statusTone = $approved ? 'good' : ($changesRequested ? 'bad' : ($submitted ? 'run' : 'warn'));
    $nextLabel = $isAdmin ? ($approved ? 'Review Complete' : ($submitted ? 'Approve or Return Report' : 'Waiting on Streamer')) : ($changesRequested ? 'Fix Requested Changes' : ($submitted ? 'Waiting for Admin Review' : 'Complete & Submit Report'));
@endphp

<x-filament-panels::page x-data="wizardData()" @items-added.window="itemsAdded(); showItemsModal = false">
<style>
.vx-log{max-width:1380px;margin:0 auto;display:grid;gap:14px}.vx-card{border:1px solid #e5e7eb;background:#fff;border-radius:18px;box-shadow:0 1px 2px rgba(15,23,42,.04);overflow:hidden}.dark .vx-card{border-color:#263248;background:#101827}.vx-pad{padding:18px}.vx-chip{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:10px;font-weight:800;background:#f3f4f6;color:#4b5563}.dark .vx-chip{background:#1f2937;color:#d1d5db}.vx-chip.good{background:#ecfdf5;color:#047857}.vx-chip.bad{background:#fef2f2;color:#b91c1c}.vx-chip.run{background:#eff6ff;color:#1d4ed8}.vx-chip.warn{background:#fff7ed;color:#c2410c}.vx-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-top:14px}.vx-kpi{border-radius:14px;background:#f8fafc;padding:11px}.dark .vx-kpi{background:#1f2937}.vx-kpi label{display:block;font-size:9px;text-transform:uppercase;letter-spacing:.07em;font-weight:800;color:#94a3b8}.vx-kpi strong{display:block;margin-top:3px;font-size:19px;color:#111827}.dark .vx-kpi strong{color:#fff}.vx-next{border-radius:14px;padding:12px 13px;background:#f8fafc}.dark .vx-next{background:#1f2937}.vx-tabs{display:flex;gap:6px;overflow-x:auto;padding:10px}.vx-tab{display:inline-flex;align-items:center;gap:6px;min-height:40px;border-radius:11px;padding:8px 12px;font-size:11px;font-weight:800;white-space:nowrap;color:#64748b}.vx-tab.active{background:#2563eb;color:#fff}.vx-item{border:1px solid #e5e7eb;border-radius:13px;padding:11px}.dark .vx-item{border-color:#374151}.vx-actions{display:flex;flex-wrap:wrap;gap:8px}.vx-btn{display:inline-flex;align-items:center;justify-content:center;min-height:42px;border-radius:11px;border:1px solid #d1d5db;padding:8px 12px;font-size:11px;font-weight:800;color:#374151}.dark .vx-btn{border-color:#475569;color:#e5e7eb}.vx-btn.primary{background:#2563eb;border-color:#2563eb;color:#fff}.vx-review-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}.vx-review-stat{border-radius:13px;background:#f8fafc;padding:11px}.dark .vx-review-stat{background:#1f2937}.vx-review-stat label{display:block;font-size:9px;text-transform:uppercase;font-weight:800;color:#94a3b8}.vx-review-stat strong{display:block;margin-top:3px;font-size:18px}.vx-modal{position:fixed;inset:0;z-index:50;background:white;display:flex;flex-direction:column}.dark .vx-modal{background:#0f172a}
@media(max-width:760px){.vx-pad{padding:14px}.vx-kpis{grid-template-columns:1fr 1fr}.vx-review-grid{grid-template-columns:1fr 1fr}.vx-actions{display:grid;grid-template-columns:1fr 1fr}.vx-actions>*{width:100%}.vx-item-grid{grid-template-columns:1fr!important}.vx-modal-head{padding:14px!important}.vx-modal-head h1{font-size:18px!important}}
</style>

<div class="vx-log">
    @if($show)
    <section class="vx-card vx-pad">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <div class="text-[10px] font-bold uppercase tracking-[.14em] text-primary-600">{{ $isAdmin ? 'Admin Review Workspace' : 'Streamer Report' }}</div>
                <h2 class="mt-1 truncate text-xl font-bold text-gray-950 dark:text-white">{{ $show->title ?? 'Untitled Show' }}</h2>
                <div class="mt-1 text-[10px] text-gray-500">{{ $show->show_date?->format('M j, Y g:i A') ?? 'Date not set' }} · {{ $show->channel?->name ?? 'Unknown channel' }} · {{ $record->streamer?->name ?? 'No streamer' }}</div>
                <div class="mt-3 flex flex-wrap gap-2"><span class="vx-chip {{ $statusTone }}">{{ $statusLabel }}</span>@if(!$canEdit)<span class="vx-chip">Locked</span>@endif</div>
            </div>
            <a href="{{ \App\Filament\Resources\ShowResource::getUrl('view',['record'=>$show]) }}" class="vx-btn">← Show Workspace</a>
        </div>
        <div class="vx-kpis"><div class="vx-kpi"><label>Revenue</label><strong>${{ number_format((float)$record->gross_revenue,2) }}</strong></div><div class="vx-kpi"><label>Items</label><strong>{{ $totalItems }}</strong></div><div class="vx-kpi"><label>Units</label><strong>{{ $totalQuantity }}</strong></div><div class="vx-kpi"><label>Product Cost</label><strong>${{ number_format($totalCost,2) }}</strong></div></div>
        <div class="vx-next mt-4"><div class="text-[9px] font-bold uppercase tracking-[.12em] text-gray-400">Next action</div><div class="mt-1 text-sm font-bold text-gray-900 dark:text-white">{{ $nextLabel }}</div><div class="mt-1 text-[11px] text-gray-500">@if($isAdmin){{ $approved ? 'The report is approved and can move into fulfillment.' : ($submitted ? 'Verify the item list, costs, and stream details, then approve or return it.' : 'The streamer has not submitted this report yet.') }}@else{{ $changesRequested ? ($record->approval_notes ?: 'Review the admin notes, make corrections, and resubmit.') : ($submitted ? 'Your report is with admin review. Edit availability depends on the submission window.' : 'Confirm items and details, then submit for admin review.') }}@endif</div></div>
    </section>

    @if($canEdit)
    <section class="vx-card">
        <div class="vx-tabs" role="tablist">
            <button @click="activeTab='items'" :class="activeTab==='items'?'active':''" class="vx-tab">1 · Items</button>
            <button @click="activeTab='details'" :class="activeTab==='details'?'active':''" class="vx-tab">2 · Details</button>
            <button @click="activeTab='review'" :class="activeTab==='review'?'active':''" class="vx-tab">3 · {{ $isAdmin ? 'Review & Approve' : 'Review & Submit' }}</button>
        </div>

        <div class="border-t border-gray-100 p-4 dark:border-gray-800 sm:p-5">
            <div x-show="activeTab==='items'" x-transition>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h3 class="text-sm font-bold text-gray-950 dark:text-white">Items Sold</h3><p class="mt-1 text-[11px] text-gray-500">Confirm the physical products and quantities before moving on.</p></div><button @click="showItemsModal=true" class="vx-btn primary">{{ $totalItems>0?'Add / Update Items':'Add Items' }}</button></div>
                @if($totalItems>0)<div class="vx-item-grid mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">@foreach($orders as $order)@php $item=$order->inventoryItem; @endphp<div class="vx-item"><div class="text-sm font-bold text-gray-900 dark:text-white">{{ $item?->name ?? 'Unknown item' }}</div>@if($item?->sku)<div class="mt-1 text-[10px] font-mono text-gray-500">{{ $item->sku }}</div>@endif<div class="mt-3 flex items-center justify-between text-xs"><span>Qty <strong>{{ $order->quantity }}</strong></span><span>Cost <strong>${{ number_format((float)$order->total_cost,2) }}</strong></span></div></div>@endforeach</div>@else<div class="mt-4 rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700">No items logged yet. Add the products sold in this show.</div>@endif
            </div>

            <div x-show="activeTab==='details'" x-transition style="display:none"><div class="mb-4 rounded-xl bg-blue-50 p-3 text-[11px] text-blue-700 dark:bg-blue-950/30 dark:text-blue-300">Complete the stream details and financial inputs. Required fields must be complete before submission or approval.</div>{{ $this->form }}</div>

            <div x-show="activeTab==='review'" x-transition style="display:none">
                <div class="vx-review-grid"><div class="vx-review-stat"><label>Items Logged</label><strong>{{ $totalItems }}</strong></div><div class="vx-review-stat"><label>Total Item Cost</label><strong>${{ number_format($totalCost,2) }}</strong></div><div class="vx-review-stat"><label>Hours Streamed</label><strong>{{ number_format((float)$record->hours_streamed,2) }}</strong></div></div>
                <div class="mt-4 rounded-xl {{ $approved?'bg-green-50 text-green-700 dark:bg-green-950/30 dark:text-green-300':($changesRequested?'bg-red-50 text-red-700 dark:bg-red-950/30 dark:text-red-300':'bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-200') }} p-4"><div class="text-xs font-bold">{{ $approved?'Approved':($changesRequested?'Changes requested':($isAdmin?'Admin decision':'Ready to submit')) }}</div><div class="mt-1 text-[11px]">{{ $approved?'This report is approved and ready for the next workflow stage.':($changesRequested?($record->approval_notes ?: 'Admin returned this report for corrections.'):($isAdmin?'Use the page actions above to approve or reject after verifying this summary.':'Use Submit for Review when everything is correct.')) }}</div></div>
                <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800"><div class="vx-actions justify-end">@foreach($this->getFormActions() as $action){{ $action }}@endforeach</div></div>
            </div>
        </div>
    </section>
    @else
    <section class="vx-card vx-pad"><div class="rounded-xl bg-blue-50 p-4 text-sm text-blue-700 dark:bg-blue-950/30 dark:text-blue-300"><strong>View only.</strong> This report is locked for editing. Use the available page actions to request or grant edit access when appropriate.</div></section>
    @endif
    @endif
</div>

<div x-show="showItemsModal" class="vx-modal" style="display:none">
    <div class="vx-modal-head flex shrink-0 items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700 dark:bg-gray-900"><div><h1 class="text-2xl font-bold text-gray-950 dark:text-white">Select Items Sold</h1><p class="mt-1 text-sm text-gray-500">Search inventory, set quantity, and attach products to this show.</p></div><button @click="showItemsModal=false" class="min-h-11 rounded-xl border border-gray-300 px-4 text-sm font-bold dark:border-gray-600">Close</button></div>
    <div class="flex-1 overflow-hidden">@livewire('streamer-log-items-modal',['recordId'=>$record->id,'title'=>'Select Items Sold','description'=>'Search and select inventory items for this show','multiSelect'=>true,'allowQuantityInput'=>true,'allowCostInput'=>true,'allowCreateItem'=>true,'successEvent'=>'items-added'],key('items-modal-'.$record->id))</div>
</div>

<script>
function wizardData(){return{activeTab:'items',showItemsModal:false,itemsAdded(){window.location.reload()}}}
</script>
</x-filament-panels::page>