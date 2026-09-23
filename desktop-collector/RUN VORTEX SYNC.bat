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
  powershell -NoProfile -ExecutionPolicy Bypass -Command "$p=$env:SCRIPT_DIR+'config.json';$j=Get-Content -LiteralPath $p -Raw|ConvertFrom-Json;$j.api_url='https://vortexops.tech/api';$j.api_token=$env:VORTEX_TOKEN;$j.headless=$false;$j.profile_dir='';$json=$j|ConvertTo-Json -Depth 10;[System.IO.File]::WriteAllText($p,$json,(New-Object System.Text.UTF8Encoding($false)))"
  if errorlevel 1 goto :CONFIGFAIL
)

python -c "from scrapling.fetchers import DynamicSession" >nul 2>&1
if errorlevel 1 (
  echo Installing/updating Scrapling browser requirements...
  python -m pip install --upgrade -r "%SCRIPT_DIR%requirements.txt"
  if errorlevel 1 (
    echo ERROR: Scrapling browser dependency install failed.
    pause
    exit /b 1
  )
  python -c "from scrapling.fetchers import DynamicSession" >nul 2>&1
  if errorlevel 1 (
    echo ERROR: Scrapling browser runtime is still incomplete after installation.
    pause
    exit /b 1
  )
)

set "SOURCE_CHROME_USER_DATA=%LOCALAPPDATA%\Google\Chrome\User Data"
set "CHROME_USER_DATA=%LOCALAPPDATA%\VortexOps\WhatnotCollector\ChromeProfile"

if not exist "%SOURCE_CHROME_USER_DATA%" (
  echo ERROR: Could not find your normal Google Chrome profile.
  pause
  exit /b 1
)

echo.
echo IMPORTANT: Close ALL Google Chrome windows for this first profile copy.
echo Vortex will COPY your Chrome profile into its own dedicated collector
echo profile. Future syncs will not need to control your normal Chrome.
echo.
pause

if not exist "%CHROME_USER_DATA%\Local State" (
  echo Creating dedicated Vortex Chrome profile...
  if not exist "%CHROME_USER_DATA%" mkdir "%CHROME_USER_DATA%"
  robocopy "%SOURCE_CHROME_USER_DATA%" "%CHROME_USER_DATA%" /E /COPY:DAT /DCOPY:DAT /R:1 /W:1 /XD "Cache" "Code Cache" "GPUCache" "ShaderCache" "GrShaderCache" "Crashpad" /XF "SingletonLock" "SingletonCookie" "SingletonSocket" >nul
  set "ROBOCOPY_EXIT=%ERRORLEVEL%"
  if %ERRORLEVEL% GEQ 8 (
    echo ERROR: Could not copy your Chrome profile into the Vortex collector profile.
    pause
    exit /b 1
  )
)

powershell -NoProfile -ExecutionPolicy Bypass -Command "$p=$env:SCRIPT_DIR+'config.json';$j=Get-Content -LiteralPath $p -Raw|ConvertFrom-Json;$j.profile_dir=$env:CHROME_USER_DATA;$j.headless=$false;$json=$j|ConvertTo-Json -Depth 10;[System.IO.File]::WriteAllText($p,$json,(New-Object System.Text.UTF8Encoding($false)))"
if errorlevel 1 goto :CONFIGFAIL

echo.
echo Starting Vortex Whatnot sync using dedicated profile:
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
