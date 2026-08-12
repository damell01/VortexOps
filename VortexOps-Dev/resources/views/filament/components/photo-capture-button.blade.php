<div class="flex gap-2">
    <button
        type="button"
        onclick="window.dispatchEvent(new Event('open-photo-capture'))"
        class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 active:scale-95 transition-transform"
        title="Take a photo with your camera"
    >
        📷 Take Photo
    </button>
</div>

<script>
document.addEventListener('photo-captured', function(e) {
    const file = e.detail?.file;
    if (!file) return;

    // Find the file upload field and add the captured photo
    const fileInput = document.querySelector('input[type="file"][accept*="image"]');
    if (!fileInput) {
        console.warn('[photo-capture] Could not find file upload input');
        return;
    }

    // Create a DataTransfer object to simulate file selection
    const dataTransfer = new DataTransfer();

    // Get existing files from the input
    if (fileInput.files) {
        for (let i = 0; i < fileInput.files.length; i++) {
            dataTransfer.items.add(fileInput.files[i]);
        }
    }

    // Add the captured photo
    dataTransfer.items.add(file);

    // Set the files on the input
    fileInput.files = dataTransfer.files;

    // Trigger change event for Livewire/Filament to detect the update
    fileInput.dispatchEvent(new Event('change', { bubbles: true }));
    fileInput.dispatchEvent(new Event('input', { bubbles: true }));

    // Show success message
    if (window.toastr) {
        toastr.success('Photo captured and added to uploads');
    }
});
</script>
