<div class="md:hidden sticky top-0 z-50 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
    <div class="flex items-center justify-between px-4 py-3">
        <!-- Mobile Menu Button -->
        <button
            id="mobile-menu-btn"
            class="mobile-menu-toggle"
            aria-label="Toggle navigation menu"
            aria-expanded="false"
            aria-controls="mobile-nav"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        <!-- Logo/Brand -->
        <div class="flex-1 text-center mx-4">
            <h1 class="text-lg font-bold text-gray-900 dark:text-white truncate">VortexOps</h1>
        </div>

        <!-- Quick Action Icons -->
        <div class="flex items-center gap-2">
            <!-- Search Icon -->
            <button
                id="mobile-search-btn"
                class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                aria-label="Quick search"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </button>

            <!-- Notifications Icon -->
            <button
                id="mobile-notify-btn"
                class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition relative"
                aria-label="Notifications"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <span class="absolute top-1 right-1 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full">0</span>
            </button>
        </div>
    </div>

    <!-- Mobile Search Bar (Hidden by default) -->
    <div id="mobile-search-bar" class="hidden px-4 py-3 border-t border-gray-200 dark:border-gray-700">
        <input
            type="search"
            placeholder="Search..."
            class="w-full px-3 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm placeholder-gray-500 dark:placeholder-gray-400"
            id="mobile-search-input"
        />
    </div>
</div>

<!-- Mobile Navigation Sidebar Backdrop -->
<div
    id="mobile-nav-backdrop"
    class="hidden md:hidden fixed inset-0 z-30 bg-black/50 transition-opacity"
    aria-hidden="true"
></div>

<!-- Mobile Navigation Sidebar -->
<nav
    id="mobile-nav"
    class="hidden md:hidden fixed left-0 top-0 h-full w-80 max-w-[80vw] bg-white dark:bg-gray-800 shadow-lg z-40 overflow-y-auto -webkit-overflow-scrolling-touch transform -translate-x-full transition-transform duration-300 ease-in-out"
    aria-label="Mobile navigation"
>
    <!-- Close Button -->
    <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Menu</h2>
        <button
            id="mobile-nav-close"
            class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition"
            aria-label="Close navigation menu"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Navigation Items (Populated by Filament) -->
    <div class="py-4">
        <!-- Filament navigation will be rendered here -->
        <slot />
    </div>

    <!-- Footer Navigation Links -->
    <div class="border-t border-gray-200 dark:border-gray-700 p-4 mt-auto">
        <div class="space-y-2">
            <a href="#settings" class="block px-4 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition text-sm text-gray-700 dark:text-gray-300">
                ⚙️ Settings
            </a>
            <a href="#help" class="block px-4 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition text-sm text-gray-700 dark:text-gray-300">
                ❓ Help & Support
            </a>
            <a href="#profile" class="block px-4 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition text-sm text-gray-700 dark:text-gray-300">
                👤 Profile
            </a>
        </div>
    </div>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const menuBtn = document.getElementById('mobile-menu-btn');
        const nav = document.getElementById('mobile-nav');
        const backdrop = document.getElementById('mobile-nav-backdrop');
        const closeBtn = document.getElementById('mobile-nav-close');
        const searchBtn = document.getElementById('mobile-search-btn');
        const searchBar = document.getElementById('mobile-search-bar');
        const searchInput = document.getElementById('mobile-search-input');

        // Open menu
        menuBtn.addEventListener('click', () => {
            nav.classList.remove('-translate-x-full');
            backdrop.classList.remove('hidden');
            menuBtn.setAttribute('aria-expanded', 'true');
        });

        // Close menu
        const closeMenu = () => {
            nav.classList.add('-translate-x-full');
            backdrop.classList.add('hidden');
            menuBtn.setAttribute('aria-expanded', 'false');
        };

        closeBtn.addEventListener('click', closeMenu);
        backdrop.addEventListener('click', closeMenu);

        // Toggle search
        searchBtn.addEventListener('click', () => {
            searchBar.classList.toggle('hidden');
            if (!searchBar.classList.contains('hidden')) {
                searchInput.focus();
            }
        });

        // Close menu when clicking a link
        nav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', closeMenu);
        });
    });
</script>

<style>
    @media (max-width: 768px) {
        #mobile-nav {
            max-height: 100vh;
        }

        #mobile-nav-backdrop {
            display: none;
        }

        #mobile-nav-backdrop.show {
            display: block;
        }
    }
</style>
