<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Whatnot API Health — VortexOps</title>
<style>
:root{color-scheme:dark}*{box-sizing:border-box}body{margin:0;background:#0c1018;color:#e4e9f3;font:15px system-ui,-apple-system,Segoe UI,sans-serif}main{max-width:1150px;margin:auto;padding:28px 20px}a{color:#c4b5fd}h1{font-size:27px;margin:12px 0 6px}p{color:#9ba7ba;line-height:1.5}.eyebrow{color:#a78bfa;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px;margin-top:24px}.card{background:#151b27;border:1px solid #30394b;border-radius:14px;padding:20px}.card h2{font-size:18px;margin:0 0 14px}.meta{font-size:13px;color:#a9b3c4;margin:8px 0}.status{display:inline-block;background:#252d3c;border-radius:999px;padding:5px 10px;font-size:12px}.note{border:1px solid #544528;background:#242017;border-radius:10px;padding:13px;margin-top:18px;color:#f2d899}.actions{display:flex;gap:8px;margin-top:16px;flex-wrap:wrap}button{background:#6d4bce;color:white;border:0;border-radius:8px;padding:10px 13px;font-weight:650;cursor:pointer}button:disabled{opacity:.5;cursor:wait}button.secondary{background:#30394b}input{width:100%;background:#0c1018;color:white;border:1px solid #465166;border-radius:8px;padding:11px;margin-top:12px}small{color:#9ba7ba}output{display:block;min-height:20px;margin-top:12px;color:#c4b5fd;font-size:13px;overflow-wrap:anywhere}
</style>
</head>
<body>
<main>
<a href="/admin">← Back to VortexOps</a>
<div class="eyebrow">Owner tools / Integrations</div>
<h1>Whatnot Seller API Health</h1>
<p>Configure and test each seller channel independently. Inspect captured data freshness and errors before activation. The existing scraper remains active.</p>
<div class="note">API capture is disabled by default and requires a successful connection test before activation. The scraper remains active; API snapshots do not automatically change financial allocations. Tokens are encrypted at rest and never returned to the browser.</div>
<section class="card" style="margin-top:18px" aria-label="API activation readiness">
<h2>Owner tools — API activation</h2>
<p>API capture is off by default for each channel. Saving credentials never enables capture. A successful connection test is required before enabling read-only synchronization.</p>
<div class="meta">Live requests: {{ $apiFlags['live_requests'] ? 'Allowed' : 'Disabled' }} · Contract verified: {{ $apiFlags['contract_verified'] ? 'Yes' : 'No' }}</div>
<div class="meta">Pagination verified: {{ $apiFlags['pagination_verified'] ? 'Yes' : 'No' }} · Scheduler allowed: {{ $apiFlags['scheduler_enabled'] ? 'Yes' : 'No' }}</div>
<div class="meta">Activation requires all server flags, stored credentials, and a successful test within the last 24 hours.</div>
</section>
<section class="card" style="margin-top:16px" aria-label="Ledger reconciliation">
<h2>Ledger reconciliation</h2>
<p>Review unmatched sales, refunds, tips, fees and business marketing allocations. Imports must not overwrite approved manual decisions.</p>
<div class="meta">Server diagnostics: <code>php artisan ledger:coverage --json</code> · <code>php artisan ledger:business-summary --json</code> · <code>php artisan whatnot:api-diagnose --json</code></div>
</section>
<div class="grid">
@foreach ($connections as $connection)
<section class="card" data-channel="{{ $connection['channel_id'] }}">
<h2>{{ $connection['name'] }}</h2>
<span class="status">{{ str_replace('_', ' ', ucfirst($connection['status'])) }}</span>
<div class="meta">Seller: {{ $connection['username'] ?: 'Not specified' }}</div>
<div class="meta">Credentials: {{ $connection['configured'] ? 'Stored securely' : 'Not configured' }}</div>
<div class="meta">API capture: <strong>{{ $connection['sync_enabled'] ? 'Enabled (read-only)' : 'Disabled' }}</strong></div>
<div class="meta">Live API requests: {{ $connection['live_requests_allowed'] ? 'Allowed by server configuration' : 'Blocked by server configuration' }}</div>
<div class="meta">Shows data: {{ str_replace('_', ' ', $connection['data_health']['shows']) }} | Captured: {{ $connection['snapshots']['shows']['count'] }} | Last: {{ $connection['snapshots']['shows']['last_capture'] ?: 'Never' }}</div>
<div class="meta">Reports data: {{ str_replace('_', ' ', $connection['data_health']['reports']) }} | Captured: {{ $connection['snapshots']['reports']['count'] }} | Last: {{ $connection['snapshots']['reports']['last_capture'] ?: 'Never' }}</div>
<div class="meta">Orders data: {{ str_replace('_', ' ', $connection['data_health']['orders']) }} | Captured: {{ $connection['snapshots']['orders']['count'] }} | Last: {{ $connection['snapshots']['orders']['last_capture'] ?: 'Never' }}</div>
@if(count($connection['recent_audits']))
<div class="meta"><strong>Recent capture activity</strong></div>
@foreach($connection['recent_audits'] as $audit)
<div class="meta">{{ $audit['resource'] }} — {{ $audit['status'] }} — {{ $audit['finished_at'] ?: 'Pending' }}</div>
@endforeach
@endif
<div class="meta">Last check: {{ $connection['last_checked_at'] ?: 'Never' }}</div>
<div class="meta">Last success: {{ $connection['last_success_at'] ?: 'Never' }}</div>
@if($connection['last_error']) <div class="meta">Last error: {{ $connection['last_error'] }}</div> @endif
<label for="token-{{ $connection['channel_id'] }}"><small>Seller API token</small></label>
<input id="token-{{ $connection['channel_id'] }}" type="password" autocomplete="off" placeholder="Paste token securely">
<div class="actions">
<button type="button" onclick="apiAction({{ $connection['channel_id'] }}, 'credentials', this)">Save token</button>
<button class="secondary" type="button" onclick="apiAction({{ $connection['channel_id'] }}, 'test', this)">Test connection</button>
<button class="secondary" type="button" @disabled(! $connection['sync_enabled'] && (! $connection['live_requests_allowed'] || $connection['status'] !== 'connected' || ! $connection['last_success_at'] || ! $apiFlags['contract_verified'] || ! $apiFlags['pagination_verified'] || ! $apiFlags['scheduler_enabled'])) onclick="toggleCapture({{ $connection['channel_id'] }}, {{ $connection['sync_enabled'] ? 'true' : 'false' }}, this)">{{ $connection['sync_enabled'] ? 'Disable API capture' : 'Enable API capture' }}</button>
</div>
<output aria-live="polite"></output>
</section>
@endforeach
</div>
</main>
<script>
async function apiAction(id, action, button) {
  const card = button.closest('.card');
  const output = card.querySelector('output');
  const input = card.querySelector('input');
  if(action === 'credentials' && !input.value.trim()){output.textContent='Enter a token first.';return;}
  button.disabled=true; output.textContent='Processing…';
  try {
    const response=await fetch('/admin/whatnot-api/channels/'+id+'/'+action,{
      method:'POST',credentials:'same-origin',
      headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
      body:action==='credentials'?JSON.stringify({token:input.value}):'{}'
    });
    const data=await response.json();
    output.textContent=response.ok?(action==='credentials'?'Token saved. API sync remains disabled.':(data.ok?'Connected successfully.':data.message||'Connection test failed.')):'Request failed ('+response.status+').';
    if(response.ok && action==='credentials')input.value='';
  } catch(e){output.textContent='Network error. Please retry.';}
  finally{button.disabled=false;}
}
async function toggleCapture(id, enabled, button) {
  const output=button.closest('.card').querySelector('output');
  // Turning capture on takes a second click on the same button (no browser dialog).
  if(!enabled && button.dataset.armed!=='1'){
    button.dataset.armed='1'; button.dataset.label=button.textContent;
    button.textContent='Click again to enable';
    output.textContent='Enables read-only API snapshot capture for this channel. The scraper stays active.';
    setTimeout(()=>{ if(button.dataset.armed==='1'){ button.dataset.armed=''; button.textContent=button.dataset.label; output.textContent=''; } },6000);
    return;
  }
  button.dataset.armed='';
  const confirmation=enabled?'':'ENABLE READ ONLY';
  button.disabled=true; output.textContent='Checking activation requirements…';
  try {
    const response=await fetch('/admin/whatnot-api/channels/'+id+'/toggle',{
      method:'POST',credentials:'same-origin',
      headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
      body:JSON.stringify({enabled:!enabled,confirmation})
    });
    const data=await response.json();
    output.textContent=data.message||'Unable to change capture state.';
    if(response.ok)window.location.reload();
  }catch(e){output.textContent='Network error. Please retry.';}
  finally{button.disabled=false;}
}
</script>
</body></html>
