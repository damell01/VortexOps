<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>@yield('code') — {{ config('app.name', 'VortexOps') }}</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{background:#0C1018;color:#DCE3EF;font-family:system-ui,-apple-system,'Segoe UI',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.wrap{max-width:480px;width:100%;text-align:center}
.code{font-family:ui-monospace,monospace;font-size:88px;font-weight:800;line-height:1;color:#7C3AED;letter-spacing:-.04em;margin-bottom:16px}
h1{font-size:22px;font-weight:700;letter-spacing:-.02em;margin-bottom:10px}
p{color:#7A8499;font-size:15px;line-height:1.6;max-width:38ch;margin:0 auto 28px}
a{display:inline-flex;align-items:center;gap:8px;background:#7C3AED;color:#fff;text-decoration:none;padding:10px 22px;border-radius:8px;font-size:14px;font-weight:600;transition:background .14s}
a:hover{background:#6D28D9}
</style>
</head>
<body>
<div class="wrap">
  <div class="code">@yield('code')</div>
  <h1>@yield('title')</h1>
  <p>@yield('message')</p>
  <a href="{{ url('/') }}">← Back to VortexOps</a>
</div>
</body>
</html>
