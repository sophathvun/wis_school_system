@echo off
setlocal
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\start-site.ps1" %*
set "site_exit_code=%errorlevel%"
if not "%site_exit_code%"=="0" pause
exit /b %site_exit_code%
