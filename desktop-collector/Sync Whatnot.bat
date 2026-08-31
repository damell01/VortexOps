@echo off
setlocal
cd /d "%~dp0"
title VortexOps Whatnot Sync

node collector.cjs
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
