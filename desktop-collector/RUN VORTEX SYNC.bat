@echo off
setlocal EnableExtensions
title VortexOps Whatnot Sync

set "SCRIPT_DIR=%~dp0"
cd /d "%SCRIPT_DIR%"

echo ============================================================
echo  VortexOps Whatnot Sync - One Click
echo ============================================================
echo.

if not exist "%SCRIPT_DIR%scrapling_collector.cjs" goto :INCOMPLETE
if not exist "%SCRIPT_DIR%scrapling_runner.py" goto :INCOMPLETE
if not exist "%SCRIPT_DIR%requirements.txt" goto :INCOMPLETE
if not exist "%SCRIPT_DIR%config.example.json" goto :INCOMPLETE

where node >nul 2>&1 || (
  echo ERROR: Node.js is not installed or not on PATH.
  pause
  exit /b 1
)
where python >nul 2>&1 || (
  echo ERROR: Python is not installed or not on PATH.
  pause
  exit /b 1
)

if not exist "%SCRIPT_DIR%config.json" (
  copy /y "%SCRIPT_DIR%config.example.json" "%SCRIPT_DIR%config.json" >nul || goto :CONFIGFAIL
  echo FIRST RUN SETUP
  echo.
  set /p "VORTEX_TOKEN=Paste the Vortex SCRAPER_API_TOKEN: "
  powershell -NoProfile -ExecutionPolicy Bypass -Command "$p=$env:SCRIPT_DIR+'config.json';$j=Get-Content -LiteralPath $p -Raw|ConvertFrom-Json;$j.api_url='https://vortexops.tech/api';$j.api_token=$env:VORTEX_TOKEN;$j.headless=$false;$j.profile_dir='';$j|ConvertTo-Json -Depth 10|Set-Content -LiteralPath $p -Encoding UTF8"
  if errorlevel 1 goto :CONFIGFAIL
)

python -c "import scrapling" >nul 2>&1
if errorlevel 1 (
  echo Installing/updating Scrapling requirements...
  python -m pip install -r "%SCRIPT_DIR%requirements.txt"
  if errorlevel 1 (
    echo ERROR: Scrapling install failed.
    pause
    exit /b 1
  )
)

for /f "usebackq delims=" %%P in (`powershell -NoProfile -Command "$p=Join-Path $env:LOCALAPPDATA 'Google\Chrome\User Data';if(Test-Path -LiteralPath $p){$p}"`) do set "CHROME_USER_DATA=%%P"
if not defined CHROME_USER_DATA (
  echo ERROR: Could not find your normal Google Chrome profile.
  pause
  exit /b 1
)

echo.
echo IMPORTANT: Close ALL Google Chrome windows before continuing.
echo The collector will use the same Chrome data where your Whatnot
echo account already shows Switch Role.
echo.
pause

powershell -NoProfile -ExecutionPolicy Bypass -Command "$p=$env:SCRIPT_DIR+'config.json';$j=Get-Content -LiteralPath $p -Raw|ConvertFrom-Json;$j.profile_dir=$env:CHROME_USER_DATA;$j.headless=$false;$j|ConvertTo-Json -Depth 10|Set-Content -LiteralPath $p -Encoding UTF8"
if errorlevel 1 goto :CONFIGFAIL

echo.
echo Starting Vortex Whatnot sync using:
echo %CHROME_USER_DATA%
echo.
node "%SCRIPT_DIR%scrapling_collector.cjs"
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

:INCOMPLETE
echo ERROR: This launcher cannot run by itself.
echo.
echo Download or clone the complete desktop-collector folder.
echo Required files include:
echo   RUN VORTEX SYNC.bat
echo   scrapling_collector.cjs
echo   scrapling_runner.py
echo   requirements.txt
echo   config.example.json
echo.
pause
exit /b 1

:CONFIGFAIL
echo ERROR: Could not create/update config.json.
echo Make sure the complete desktop-collector folder is extracted locally.
pause
exit /b 1
