$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$configPath = Join-Path $root 'config.json'
$examplePath = Join-Path $root 'config.example.json'
Write-Host ''
Write-Host '============================================================'
Write-Host ' Vortex Whatnot Sync - First Time Setup'
Write-Host '============================================================'
Write-Host ''
if (-not (Get-Command node -ErrorAction SilentlyContinue)) { Write-Host 'Node.js 18+ is required: https://nodejs.org/' -ForegroundColor Red; Read-Host 'Press Enter to close'; exit 1 }
if (-not (Get-Command python -ErrorAction SilentlyContinue)) { Write-Host 'Python 3 is required: https://www.python.org/downloads/windows/' -ForegroundColor Red; Read-Host 'Press Enter to close'; exit 1 }
$pf86 = [Environment]::GetEnvironmentVariable('ProgramFiles(x86)')
$chromeCandidates = @(
    (Join-Path $env:PROGRAMFILES 'Google\Chrome\Application\chrome.exe'),
    $(if ($pf86) { Join-Path $pf86 'Google\Chrome\Application\chrome.exe' }),
    (Join-Path $env:LOCALAPPDATA 'Google\Chrome\Application\chrome.exe')
) | Where-Object { $_ -and (Test-Path $_) }
if (-not $chromeCandidates) { Write-Host 'Google Chrome is required. Install Chrome and run this setup again.' -ForegroundColor Red; Read-Host 'Press Enter to close'; exit 1 }
Write-Host 'Installing collector browser components. This is only needed once...'
python -m pip install -r (Join-Path $root 'requirements.txt')
if ($LASTEXITCODE -ne 0) { throw 'Python dependency install failed.' }
scrapling install
if ($LASTEXITCODE -ne 0) { throw 'Scrapling browser setup failed.' }
$config = if (Test-Path $configPath) { Get-Content $configPath -Raw | ConvertFrom-Json } else { Get-Content $examplePath -Raw | ConvertFrom-Json }
$config.api_url = 'https://vortexops.tech/api'
Write-Host ''
Write-Host 'Paste the Vortex collector API token. It stays on this computer.'
$secure = Read-Host 'API token' -AsSecureString
$ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
try { $token = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr) } finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr) }
if ([string]::IsNullOrWhiteSpace($token)) { throw 'API token cannot be blank.' }
$config.api_token = $token
$config.headless = $false
$config | ConvertTo-Json -Depth 8 | Set-Content $configPath -Encoding UTF8
Write-Host ''
Write-Host 'Setup complete.' -ForegroundColor Green
Write-Host 'A dedicated Whatnot Chrome window will open next.'
Write-Host 'Log in, open Seller Hub, verify the correct seller account, then CLOSE that Chrome window.'
Read-Host 'Press Enter to open Whatnot'
node (Join-Path $root 'login.cjs')
if ($LASTEXITCODE -ne 0) { throw 'Whatnot login helper failed.' }
Write-Host ''
Write-Host 'Login saved. Starting a visible test sync...' -ForegroundColor Green
node (Join-Path $root 'scrapling_collector.cjs')
$code = $LASTEXITCODE
Write-Host ''
if ($code -eq 0) { Write-Host 'TEST SYNC COMPLETED.' -ForegroundColor Green } else { Write-Host "TEST SYNC FAILED (exit $code). Leave this window open and send the error." -ForegroundColor Red }
Read-Host 'Press Enter to close'
exit $code
