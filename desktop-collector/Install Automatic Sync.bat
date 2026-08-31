@echo off
setlocal
cd /d "%~dp0"
title VortexOps Automatic Whatnot Sync Setup

where node >nul 2>nul
if errorlevel 1 (
  echo Node.js is not installed. Run install.bat first.
  pause
  exit /b 1
)

echo ============================================================
echo  VortexOps Automatic Whatnot Sync
echo ============================================================
echo.
echo This installs a Windows Task Scheduler job named:
echo   VortexOps Whatnot Collector
echo.
echo It runs every hour while this Windows account is logged in.
echo The dedicated Whatnot Chrome profile must already be logged in.
echo.

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0install-task.ps1"
if errorlevel 1 (
  echo.
  echo Could not create the scheduled task.
  pause
  exit /b 1
)

echo.
echo Automatic hourly sync is installed.
echo You can still run "Sync Whatnot.bat" manually at any time.
echo Scheduled output is written to desktop-collector\logs\scheduled-sync.log.
echo.
pause
