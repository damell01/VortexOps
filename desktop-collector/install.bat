@echo off
setlocal
cd /d "%~dp0"
title VortexOps Whatnot Collector Setup

echo ============================================================
echo  VortexOps Whatnot Desktop Collector - Setup
echo ============================================================
echo.

where node >nul 2>nul
if errorlevel 1 (
  echo Node.js is not installed.
  echo Install Node.js 18 or newer from https://nodejs.org/ and run this again.
  echo.
  pause
  exit /b 1
)

for /f "tokens=1 delims=." %%V in ('node -p "process.versions.node"') do set NODEMAJOR=%%V
if %NODEMAJOR% LSS 18 (
  echo Node.js 18 or newer is required. Current version:
  node --version
  pause
  exit /b 1
)

echo Installing the Playwright Node package used by the shared VortexOps scraper...
call npm install -g playwright
if errorlevel 1 (
  echo.
  echo Playwright installation failed.
  pause
  exit /b 1
)

if not exist config.json (
  copy /Y config.example.json config.json >nul
  echo.
  echo Created config.json.
)

echo.
echo Setup complete.
echo.
echo NEXT:
echo   1. Open config.json and set api_url and api_token.
echo   2. Double-click "Login to Whatnot.bat" and log in once.
echo   3. Double-click "Sync Whatnot.bat".
echo.
pause
