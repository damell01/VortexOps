@echo off
setlocal
cd /d "%~dp0"
title VortexOps Whatnot Collector Setup

echo ============================================================
echo  VortexOps Whatnot Desktop Collector - Scrapling Setup
echo ============================================================
echo.

where node >nul 2>nul
if errorlevel 1 (
  echo Node.js 18 or newer is required for the VortexOps upload/orchestration layer.
  echo Install Node.js from https://nodejs.org/ and run this again.
  pause
  exit /b 1
)

where python >nul 2>nul
if errorlevel 1 (
  echo Python 3.9 or newer is required for Scrapling.
  echo Install Python from https://www.python.org/downloads/windows/ and enable "Add Python to PATH".
  pause
  exit /b 1
)

echo Installing Scrapling and its Python dependencies...
python -m pip install --upgrade pip
if errorlevel 1 goto :failed
python -m pip install -r requirements.txt
if errorlevel 1 goto :failed

echo.
echo Installing Scrapling browser dependencies...
python -m scrapling install
if errorlevel 1 (
  echo Scrapling's dependency installer returned an error.
  echo The collector still uses your installed Google Chrome, but the Python Playwright runtime must be available.
  goto :failed
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
echo   4. After that works, run "Full Historical Sync.bat" once.
echo   5. Then run "Install Automatic Sync.bat" for hourly collection.
echo.
pause
exit /b 0

:failed
echo.
echo Setup failed. Review the error above.
pause
exit /b 1
