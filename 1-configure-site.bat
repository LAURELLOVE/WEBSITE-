@echo off
cd /d "%~dp0"
echo STEP 1 - your private settings (database, email alerts, admin password)
echo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0configure-site.ps1"
echo.
pause
