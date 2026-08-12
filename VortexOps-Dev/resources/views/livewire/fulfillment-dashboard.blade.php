<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">📦 Fulfillment Center</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Manage packaging and shipping for {{ $show->primaryStreamer()?->name ?? 'Show' }}
                </p>
            </div>
            <div class="text-right">
                <div class="text-3xl font-bold text-primary-600">{{ $orders->count() }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Items to process</div>
            </div>
        </div>
    </div>

    {{-- Status Filter --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <div class="flex gap-2 flex-wrap">
            @foreach(['pending' => 'Pending', 'label_created' => 'Label Created', 'packed' => 'Packed', 'shipped' => 'Shipped', 'delivered' => 'Delivered'] as $status => $label)
                <button
                    wire:click="$set('filterStatus', '{{ $status }}')"
                    @class([
                        'px-4 py-2 rounded-lg font-medium transition',
                        'bg-primary-600 text-white' => $filterStatus === $status,
                        'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' => $filterStatus !== $status,
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Orders List --}}
    <div class="space-y-4">
        @forelse($orders as $order)
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 hover:shadow-lg transition">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $order->item_name ?? 'Unknown Item' }}
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            👤 {{ $order->buyer_display_name ?? $order->buyer_username ?? 'Unknown Buyer' }}
                        </p>
                    </div>
                    <div class="text-right">
                        <span @class([
                            'inline-block px-3 py-1 rounded-full text-xs font-medium',
                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' => $order->shipping_status === 'pending',
                            'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' => $order->shipping_status === 'label_created',
                            'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' => $order->shipping_status === 'packed',
                            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' => $order->shipping_status === 'shipped',
                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200' => $order->shipping_status === 'delivered',
                        ])>
                            {{ $statusLabels[$order->shipping_status] ?? 'Unknown' }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6 py-4 border-y border-gray-200 dark:border-gray-700">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Quantity</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $order->quantity }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Unit Price</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">${{ number_format((float) $order->unit_price, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Total Price</p>
                        <p class="text-lg font-semibold text-primary-600">${{ number_format((float) $order->total_price, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Order ID</p>
                        <p class="text-sm font-mono text-gray-600 dark:text-gray-300">{{ substr($order->whatnot_order_id ?? 'N/A', 0, 8) }}</p>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="space-y-3">
                    @if($order->shipping_status === 'pending' || $order->shipping_status === 'label_created')
                        <button
                            wire:click="markAsPacked({{ $order->id }})"
                            class="w-full bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-4 rounded-lg transition"
                        >
                            ✓ Mark as Packed
                        </button>
                    @elseif($order->shipping_status === 'packed')
                        <form wire:submit="markAsShipped({{ $order->id }}, $event.target.elements.tracking.value)">
                            <div class="flex gap-2">
                                <input
                                    type="text"
                                    name="tracking"
                                    placeholder="Enter tracking number..."
                                    class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm"
                                >
                                <button
                                    type="submit"
                                    class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-6 rounded-lg transition"
                                >
                                    🚚 Ship
                                </button>
                            </div>
                        </form>
                    @elseif($order->shipping_status === 'shipped')
                        <div class="flex items-center gap-2 p-3 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                            <span class="text-2xl">✓</span>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">Shipped</p>
                                @if($order->tracking_number)
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Tracking: {{ $order->tracking_number }}</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Shipping Details --}}
                    @if($order->shipping_amount || $order->shipping_carrier)
                        <div class="grid grid-cols-2 gap-2 text-sm bg-gray-50 dark:bg-gray-700/50 p-3 rounded-lg">
                            @if($order->shipping_amount)
                                <div>
                                    <p class="text-gray-500 dark:text-gray-400">Shipping Cost</p>
                                    <p class="font-semibold text-gray-900 dark:text-white">${{ number_format((float) $order->shipping_amount, 2) }}</p>
                                </div>
                            @endif
                            @if($order->shipping_carrier)
                                <div>
                                    <p class="text-gray-500 dark:text-gray-400">Carrier</p>
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $order->shipping_carrier }}</p>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="text-center py-12">
                <div class="text-4xl mb-2">📭</div>
                <p class="text-gray-500 dark:text-gray-400">No orders with status "{{ ucfirst(str_replace('_', ' ', $filterStatus)) }}"</p>
            </div>
        @endforelse
    </div>

    {{-- Summary --}}
    @if($orders->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 dark:from-yellow-900/20 dark:to-yellow-900/10 rounded-lg p-4 border border-yellow-200 dark:border-yellow-800">
                <p class="text-sm text-gray-600 dark:text-gray-400">Pending Shipment</p>
                <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $orders->where('shipping_status', '!=', 'shipped')->where('shipping_status', '!=', 'delivered')->count() }}</p>
            </div>
            <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-900/10 rounded-lg p-4 border border-green-200 dark:border-green-800">
                <p class="text-sm text-gray-600 dark:text-gray-400">Shipped</p>
                <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $orders->where('shipping_status', 'shipped')->count() }}</p>
            </div>
            <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 dark:from-emerald-900/20 dark:to-emerald-900/10 rounded-lg p-4 border border-emerald-200 dark:border-emerald-800">
                <p class="text-sm text-gray-600 dark:text-gray-400">Delivered</p>
                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $orders->where('shipping_status', 'delivered')->count() }}</p>
            </div>
        </div>
    @endif
</div>
