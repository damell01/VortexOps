@echo off
setlocal
cd /d "%~dp0"

if not exist logs mkdir logs

echo.>> "logs\scheduled-sync.log"
echo ============================================================>> "logs\scheduled-sync.log"
echo [%date% %time%] Starting scheduled Whatnot Scrapling sync>> "logs\scheduled-sync.log"
node scrapling_collector.cjs >> "logs\scheduled-sync.log" 2>&1
set EXITCODE=%ERRORLEVEL%
echo [%date% %time%] Finished with exit code %EXITCODE%>> "logs\scheduled-sync.log"

exit /b %EXITCODE%
