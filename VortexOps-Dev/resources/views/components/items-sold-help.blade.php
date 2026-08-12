<div class="mt-6 p-4 bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800 rounded-lg">
    <div class="flex items-start gap-3">
        <div class="text-blue-600 dark:text-blue-400 mt-1">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
            </svg>
        </div>
        <div class="flex-1">
            <h4 class="font-semibold text-blue-900 dark:text-blue-100 mb-1">Mapping Progress: {{ $mapped }} of {{ $total }} items</h4>
            <ul class="text-sm text-blue-800 dark:text-blue-200 space-y-1">
                <li>✓ <strong>Red highlighted rows</strong> = items still need mapping</li>
                <li>✓ Click the "Inventory Item" column to select what you sold</li>
                <li>✓ Use <strong>"Fill Costs"</strong> button to auto-populate unit costs from inventory</li>
                <li>✓ Mapping helps track your product costs for accurate profit sharing</li>
            </ul>
        </div>
    </div>
</div>
