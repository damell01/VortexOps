@echo off
setlocal
cd /d "%~dp0"
title Vortex Whatnot Sync Setup
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0first-setup.ps1"
