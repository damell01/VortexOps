{{-- Infinite Scroll Container Component --}}
<div class="infinite-scroll-container"
     data-load-more="{{ $loadMoreUrl }}"
     data-page-param="{{ $pageParam ?? 'page' }}">

    {{ $slot }}

    <div class="infinite-scroll-loader">
        <div class="infinite-scroll-spinner"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const containers = document.querySelectorAll('.infinite-scroll-container');

    containers.forEach(container => {
        const loadMoreUrl = container.dataset.loadMore;
        const pageParam = container.dataset.pageParam || 'page';

        if (!loadMoreUrl) return;

        let isLoading = false;
        let currentPage = 1;
        let hasMore = true;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && hasMore && !isLoading) {
                    isLoading = true;

                    // Load more items
                    const separator = loadMoreUrl.includes('?') ? '&' : '?';
                    const fetchUrl = `${loadMoreUrl}${separator}${pageParam}=${currentPage + 1}`;

                    fetch(fetchUrl, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.items && data.items.length > 0) {
                            const loader = container.querySelector('.infinite-scroll-loader');
                            const itemsHtml = data.items.join('');
                            loader.insertAdjacentHTML('beforebegin', itemsHtml);
                            currentPage++;
                        } else {
                            hasMore = false;
                            const loader = container.querySelector('.infinite-scroll-loader');
                            if (loader) {
                                loader.innerHTML = '<div class="infinite-scroll-end">No more items to load</div>';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Infinite scroll error:', error);
                        const loader = container.querySelector('.infinite-scroll-loader');
                        if (loader) {
                            loader.innerHTML = `
                                <div class="infinite-scroll-error">
                                    Failed to load more items
                                    <button class="infinite-scroll-retry" onclick="location.reload()">Retry</button>
                                </div>
                            `;
                        }
                    })
                    .finally(() => {
                        isLoading = false;
                    });
                }
            });
        }, {
            root: null,
            rootMargin: '200px',
            threshold: 0.1
        });

        // Observe the loader element
        const loader = container.querySelector('.infinite-scroll-loader');
        if (loader) {
            observer.observe(loader);
        }
    });
});
</script>
