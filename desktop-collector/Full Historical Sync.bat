@echo off
setlocal
cd /d "%~dp0"
title VortexOps Whatnot Full Historical Sync

echo ============================================================
echo  VortexOps Whatnot - FULL HISTORICAL SYNC
echo ============================================================
echo.
echo This can take a long time. It walks older analytics in batches,
echo pulls orders and shipments for every discovered show, and imports
echo the ledger in Whatnot-compatible 31-day windows.
echo.

node collector.cjs --full
set EXITCODE=%ERRORLEVEL%

echo.
if not "%EXITCODE%"=="0" (
  echo Historical sync finished with an error. Review the message above.
) else (
  echo Historical sync completed successfully.
)
echo.
pause
exit /b %EXITCODE%
