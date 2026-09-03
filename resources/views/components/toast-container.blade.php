{{-- Toast Notification Container --}}
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-3 pointer-events-none md:top-6 md:right-6">
    {{-- Toasts will be inserted here via JavaScript --}}
</div>

{{-- Mobile pallet / manifest UX fixes are kept in one partial so the rules also
     survive Filament SPA navigation without needing a separate Vite entry. --}}
@include('filament.components.pallet-mobile-ui-fixes')
@include('filament.components.mobile-inventory-hotfixes')
@include('filament.components.inventory-catalog-enhancements')
@include('filament.components.ios-inventory-picker-fix')
@include('filament.components.manifest-inventory-picker-fix')

<script>
    /**
     * Toast Notification System
     * Usage: window.showToast('Success message', 'success', 3000)
     * Types: 'success', 'error', 'warning', 'info'
     */
    window.showToast = function(message, type = 'info', duration = 3000) {
        const container = document.getElementById('toast-container');
        if (!container) return;

        // Create toast element
        const toast = document.createElement('div');
        toast.className = `animate-slide-in-from-right pointer-events-auto`;

        // Icon and color based on type
        const icons = {
            success: { icon: '✅', bg: 'bg-green-500', text: 'text-green-50' },
            error: { icon: '❌', bg: 'bg-red-500', text: 'text-red-50' },
            warning: { icon: '⚠️', bg: 'bg-amber-500', text: 'text-amber-50' },
            info: { icon: 'ℹ️', bg: 'bg-blue-500', text: 'text-blue-50' },
        };

        const config = icons[type] || icons.info;

        toast.innerHTML = `
            <div class="flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg ${config.bg} ${config.text} font-medium text-sm max-w-sm animate-bounce-in">
                <span class="text-xl">${config.icon}</span>
                <span class="flex-1">${message}</span>
                <button onclick="this.closest('div').parentElement.remove()" class="flex-shrink-0 ml-2 p-1 hover:opacity-80 active:opacity-60 transition-opacity touch-manipulation" style="min-width: 32px; min-height: 32px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; cursor: pointer; -webkit-user-select: none; user-select: none;">
                    ✕
                </button>
            </div>
        `;

        container.appendChild(toast);

        // Auto-remove after duration
        if (duration > 0) {
            setTimeout(() => {
                toast.style.animation = 'slideOutToRight 0.3s ease-in forwards';
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }

        return toast;
    };

    // Aliases for convenience
    window.toast = {
        success: (msg, duration) => window.showToast(msg, 'success', duration),
        error: (msg, duration) => window.showToast(msg, 'error', duration),
        warning: (msg, duration) => window.showToast(msg, 'warning', duration),
        info: (msg, duration) => window.showToast(msg, 'info', duration),
    };
</script>
