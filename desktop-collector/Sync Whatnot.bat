@echo off
setlocal
cd /d "%~dp0"
title VortexOps Whatnot Sync - Scrapling

node scrapling_collector.cjs
set EXITCODE=%ERRORLEVEL%

echo.
if not "%EXITCODE%"=="0" (
  echo Sync finished with an error. Review the message above.
) else (
  echo Sync completed successfully.
)
echo.
pause
exit /b %EXITCODE%
