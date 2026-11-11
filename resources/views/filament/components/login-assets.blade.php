<link rel="preload" href="{{ asset('css/vortex-login.css') }}?v=6" as="style">
<link rel="stylesheet" href="{{ asset('css/vortex-login.css') }}?v=6">
<link rel="stylesheet" href="{{ asset('css/vortex-login-wall.css') }}?v={{ filemtime(public_path('css/vortex-login-wall.css')) }}">
<script>
try {
    const entry = JSON.parse(sessionStorage.getItem('vx-workspace-entry') || 'null');
    if (entry && Date.now() - entry.at < 30000) document.documentElement.classList.add('vx-workspace-pending');
} catch (_) {}
</script>
<script src="{{ asset('js/vortex-login.js') }}?v=5" defer></script>
