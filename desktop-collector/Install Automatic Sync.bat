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

set "NODEEXE="
for /f "delims=" %%N in ('where node') do if not defined NODEEXE set "NODEEXE=%%N"
set "COLLECTOR=%~dp0collector.cjs"
set "TASKCMD=\"%NODEEXE%\" \"%COLLECTOR%\""

echo This installs a Windows Task Scheduler job named:
echo   VortexOps Whatnot Collector
echo.
echo It runs every hour while this Windows account is logged in.
echo The dedicated Whatnot Chrome profile must already be logged in.
echo.

schtasks /Create /F /TN "VortexOps Whatnot Collector" /SC HOURLY /MO 1 /TR "%TASKCMD%" /RL LIMITED
if errorlevel 1 (
  echo.
  echo Could not create the scheduled task.
  pause
  exit /b 1
)

echo.
echo Automatic hourly sync is installed.
echo You can still run "Sync Whatnot.bat" manually at any time.
echo.
pause
