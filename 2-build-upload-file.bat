@echo off
cd /d "%~dp0"
echo STEP 2 - building the file to upload for www.camoncenter.org
echo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0build-for-hosting.ps1" -Domain https://www.camoncenter.org
echo.
echo Upload this file to Network Solutions: %~dp0coc-website-upload.zip
echo.
pause
