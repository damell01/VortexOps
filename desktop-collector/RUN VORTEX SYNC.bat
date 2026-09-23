@echo off
setlocal
title VortexOps Whatnot Sync
cd /d "%~dp0"

echo ============================================================
echo  VortexOps Whatnot Sync - One Click
echo ============================================================
echo.

where node >nul 2>&1 || (
  echo ERROR: Node.js is not installed or not on PATH.
  echo Install Node.js once, then run this file again.
  pause
  exit /b 1
)

where python >nul 2>&1 || (
  echo ERROR: Python is not installed or not on PATH.
  echo Install Python once, then run this file again.
  pause
  exit /b 1
)

if not exist "config.json" (
  copy /y "config.example.json" "config.json" >nul
  echo FIRST RUN SETUP
  echo.
  set /p VORTEX_TOKEN=Paste the Vortex SCRAPER_API_TOKEN: 
  powershell -NoProfile -ExecutionPolicy Bypass -Command "$p='config.json';$j=Get-Content $p -Raw|ConvertFrom-Json;$j.api_url='https://vortexops.tech/api';$j.api_token=$env:VORTEX_TOKEN;$j.headless=$false;$j.profile_dir='';$j|ConvertTo-Json -Depth 10|Set-Content $p -Encoding UTF8"
  if errorlevel 1 (
    echo ERROR: Could not save config.json.
    pause
    exit /b 1
  )
)

python -c "import scrapling" >nul 2>&1
if errorlevel 1 (
  echo Installing/updating Scrapling requirements...
  python -m pip install -r requirements.txt
  if errorlevel 1 (
    echo ERROR: Scrapling install failed.
    pause
    exit /b 1
  )
)

echo.
echo IMPORTANT:
echo Close ALL normal Google Chrome windows before continuing.
echo The collector will use your existing Chrome profile so it can see
echo the same Whatnot login and Switch Role access you already have.
echo.
pause

for /f "usebackq delims=" %%P in (`powershell -NoProfile -Command "$p=Join-Path $env:LOCALAPPDATA 'Google\Chrome\User Data'; if(Test-Path $p){$p}"`) do set "CHROME_USER_DATA=%%P"
if not defined CHROME_USER_DATA (
  echo ERROR: Could not find your normal Google Chrome profile.
  pause
  exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -Command "$p='config.json';$j=Get-Content $p -Raw|ConvertFrom-Json;$j.profile_dir=$env:CHROME_USER_DATA;$j.headless=$false;$j|ConvertTo-Json -Depth 10|Set-Content $p -Encoding UTF8"

echo.
echo Starting Vortex Whatnot sync using:
echo %CHROME_USER_DATA%
echo.
node scrapling_collector.cjs
set "EXITCODE=%ERRORLEVEL%"
echo.
if "%EXITCODE%"=="0" (
  echo ============================================================
  echo  SYNC COMPLETE
  echo ============================================================
) else (
  echo ============================================================
  echo  SYNC FAILED - exit code %EXITCODE%
  echo  Leave this window open and send the output for review.
  echo ============================================================
)
echo.
pause
exit /b %EXITCODE%
