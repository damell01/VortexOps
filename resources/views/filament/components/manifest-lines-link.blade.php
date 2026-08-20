@php($pallet = $this->record ?? null)

@if($pallet)
    {{-- Its own Livewire component so paging does not reload this form and take
         unsaved edits with it. --}}
    @livewire('manifest-lines-summary', ['pallet' => $pallet], key('manifest-' . $pallet->id))
@else
    <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-600 px-5 py-6 text-center">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Save the pallet first — you will land straight on its manifest table.
        </p>
    </div>
@endif
