{{-- Bottom Action Bar for Mobile Table Bulk Actions --}}
<div class="bottom-action-bar" id="bottom-action-bar">
    <div class="bottom-action-bar-content">
        <div class="bottom-action-bar-info">
            <strong id="selected-count">0</strong> items selected
        </div>
        <div class="bottom-action-bar-actions">
            {{ $slot }}
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const actionBar = document.getElementById('bottom-action-bar');
    const selectedCountEl = document.getElementById('selected-count');

    // Update count when checkboxes change
    document.addEventListener('change', (e) => {
        if (e.target.type === 'checkbox') {
            const checkedCount = document.querySelectorAll('input[type="checkbox"]:checked').length;

            if (checkedCount > 0) {
                actionBar.classList.add('active');
                selectedCountEl.textContent = checkedCount;
            } else {
                actionBar.classList.remove('active');
                selectedCountEl.textContent = '0';
            }
        }
    });
});
</script>
