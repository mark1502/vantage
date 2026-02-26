@echo off
setlocal EnableDelayedExpansion

set "url=%~1"
set "filepath=!url:~21!"
set "filepath=!filepath:%%5C=\!"

start "" "!filepath!"
