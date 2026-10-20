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
.mon{position:relative;width:132px;height:112px;margin:0 auto 6px;display:flex;align-items:flex-end;justify-content:center}
.mon::after{content:"";position:absolute;bottom:2px;left:50%;width:84px;height:12px;border-radius:50%;background:radial-gradient(closest-side,#7C3AED55,transparent);transform:translateX(-50%)}
.mon img{position:relative;z-index:1;height:96px;width:auto;image-rendering:pixelated;animation:mon-in .6s cubic-bezier(.3,1.4,.6,1) both,mon-bob 3.2s ease-in-out .6s infinite}
.code{animation:fade-up .5s ease-out .15s both}
.battle{position:relative;max-width:46ch;margin:0 auto 22px;padding:12px 16px;border:2px solid #2A3550;border-radius:12px;background:#111827;text-align:left;font-size:14px;line-height:1.5;color:#E6EAF3;box-shadow:inset 0 0 0 3px #0C1018,inset 0 0 0 5px #1E2836}
.battle::after{content:"";position:absolute;right:14px;bottom:10px;border:5px solid transparent;border-top-color:#8B5CF6;animation:caret 1s steps(2) infinite}
.battle span{display:inline-block;overflow:hidden;white-space:nowrap;max-width:100%;vertical-align:bottom;animation:type 1.1s steps(38) .5s both}
@keyframes mon-in{from{opacity:0;transform:translateY(14px) scale(.6)}}
@keyframes mon-bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-5px)}}
@keyframes fade-up{from{opacity:0;transform:translateY(8px)}}
@keyframes type{from{max-width:0}}
@keyframes caret{50%{opacity:0}}
@media (max-width:480px){.code{font-size:64px}.battle span{white-space:normal;animation:none}}
@media (prefers-reduced-motion:reduce){.mon img,.code,.battle span,.battle::after{animation:none!important}}
.vx-403-details{margin:0 auto 24px;max-width:36ch;text-align:left;border:1px solid #1E2836;border-radius:8px;padding:12px 14px;font-size:13px;line-height:1.5}
.vx-403-details>div{display:flex;gap:10px;padding:3px 0}
.vx-403-details dt{color:#5C6678;flex:0 0 8.5ch}
.vx-403-details dd{color:#A9B4C6;word-break:break-word}
</style>
</head>
<body>
<div class="wrap">
  @hasSection('mon')
    <div class="mon" aria-hidden="true"><img src="/img/pokemon3d/{{ trim($__env->yieldContent('mon')) }}.gif" alt=""></div>
  @endif
  <div class="code">@yield('code')</div>
  <h1>@yield('title')</h1>
  @hasSection('encounter')
    <div class="battle"><span>@yield('encounter')</span></div>
  @endif
  <p>@yield('message')</p>
  @hasSection('details')
    @yield('details')
  @endif
  <a href="{{ url('/') }}">← Back to VortexOps</a>
</div>
</body>
</html>
