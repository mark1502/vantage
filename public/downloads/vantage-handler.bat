@echo off
setlocal EnableDelayedExpansion

set "url=%~1"
set "encodedpath=!url:~20!"

for /f "usebackq delims=" %%D in (`powershell -NoProfile -Command "[uri]::UnescapeDataString('!encodedpath!')"`) do set "filepath=%%D"

start "" "!filepath!"
