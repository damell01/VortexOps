@echo off
setlocal
cd /d "%~dp0"
title VortexOps Whatnot Login
node login.cjs
echo.
pause
