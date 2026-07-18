@echo off
setlocal enabledelayedexpansion
REM ============================================================
REM  Vantage MySQL restore script - .env driven (Laragon/Windows)
REM  Restores a .sql dump into the database named in .env.
REM
REM  Usage:
REM    restore-vantage-db.bat "C:\path\to\dump.sql"
REM    restore-vantage-db.bat            (restores the NEWEST dump)
REM
REM  WARNING: this OVERWRITES the current database. It asks for
REM  confirmation first.
REM ============================================================

REM ---- Configuration -----------------------------------------
set "MYSQL=C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe"
set "ENV_FILE=C:\laragon\www\vantage\.env"
set "BACKUP_DIR=C:\Backups\vantage-db"
REM ------------------------------------------------------------

set "DB_HOST=127.0.0.1"
set "DB_PORT=3306"
set "DB_USERNAME=root"
set "DB_PASSWORD="
set "DB_DATABASE=vantage"

if not exist "%ENV_FILE%" (
    echo *** ERROR: .env not found at "%ENV_FILE%" ***
    exit /b 1
)

for /f "usebackq tokens=1,* delims==" %%a in ("%ENV_FILE%") do (
    set "KEY=%%a"
    set "VAL=%%b"
    if defined VAL set "VAL=!VAL:"=!"
    if /i "!KEY!"=="DB_HOST"     set "DB_HOST=!VAL!"
    if /i "!KEY!"=="DB_PORT"     set "DB_PORT=!VAL!"
    if /i "!KEY!"=="DB_USERNAME" set "DB_USERNAME=!VAL!"
    if /i "!KEY!"=="DB_PASSWORD" set "DB_PASSWORD=!VAL!"
    if /i "!KEY!"=="DB_DATABASE" set "DB_DATABASE=!VAL!"
)

REM ---- Determine which dump file to restore ------------------
set "DUMP=%~1"
if "%DUMP%"=="" (
    echo No file given^; selecting the newest dump in "%BACKUP_DIR%"...
    for /f "usebackq delims=" %%f in (`powershell -NoProfile -Command "Get-ChildItem -Path '%BACKUP_DIR%\%DB_DATABASE%_*.sql' ^| Sort-Object LastWriteTime -Descending ^| Select-Object -First 1 -ExpandProperty FullName"`) do set "DUMP=%%f"
)

if "%DUMP%"=="" (
    echo *** ERROR: No dump file specified and none found in "%BACKUP_DIR%". ***
    exit /b 1
)
if not exist "%DUMP%" (
    echo *** ERROR: Dump file not found: "%DUMP%" ***
    exit /b 1
)

echo(
echo ============================================================
echo  RESTORE  -  this will OVERWRITE data in the database.
echo ------------------------------------------------------------
echo  Target DB : %DB_DATABASE%  (host %DB_HOST%:%DB_PORT%, user %DB_USERNAME%)
echo  From file : %DUMP%
echo ============================================================
echo(

set /p "CONFIRM=Type YES (all caps) to proceed: "
if not "%CONFIRM%"=="YES" (
    echo Aborted. Nothing was changed.
    exit /b 0
)

set "PASS_ARG="
if defined DB_PASSWORD if not "%DB_PASSWORD%"=="" set "PASS_ARG=--password=%DB_PASSWORD%"

REM ---- Ensure the target database exists ----------------------
echo Ensuring database "%DB_DATABASE%" exists...
"%MYSQL%" --host=%DB_HOST% --port=%DB_PORT% --user=%DB_USERNAME% %PASS_ARG% ^
  -e "CREATE DATABASE IF NOT EXISTS `%DB_DATABASE%` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if errorlevel 1 (
    echo *** ERROR: could not connect / create database. Aborting. ***
    exit /b 1
)

REM ---- Import the dump ---------------------------------------
echo Restoring... (this may take a while)
"%MYSQL%" --host=%DB_HOST% --port=%DB_PORT% --user=%DB_USERNAME% %PASS_ARG% ^
  --default-character-set=utf8mb4 ^
  "%DB_DATABASE%" < "%DUMP%"

if errorlevel 1 (
    echo(
    echo *** ERROR: restore failed. The database may be in a partial state. ***
    exit /b 1
)

echo(
echo Restore completed successfully.
endlocal
exit /b 0
