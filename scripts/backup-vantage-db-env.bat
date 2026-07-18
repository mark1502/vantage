@echo off
setlocal enabledelayedexpansion
REM ============================================================
REM  Vantage MySQL backup script - .env driven (Laragon/Windows)
REM  Reads DB credentials straight from the project's .env so it
REM  keeps working if the DB name/user/password ever changes.
REM ============================================================

REM ---- Configuration (paths only; credentials come from .env) -
set "MYSQLDUMP=C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe"
set "ENV_FILE=C:\laragon\www\vantage\.env"
set "BACKUP_DIR=C:\Backups\vantage-db"
set "RETENTION_DAYS=30"
REM ------------------------------------------------------------

REM ---- Defaults in case a key is missing from .env -----------
set "DB_HOST=127.0.0.1"
set "DB_PORT=3306"
set "DB_USERNAME=root"
set "DB_PASSWORD="
set "DB_DATABASE=vantage"

if not exist "%ENV_FILE%" (
    echo *** ERROR: .env not found at "%ENV_FILE%" ***
    exit /b 1
)

REM ---- Parse the DB_* keys out of .env -----------------------
for /f "usebackq tokens=1,* delims==" %%a in ("%ENV_FILE%") do (
    set "KEY=%%a"
    set "VAL=%%b"
    REM strip surrounding double quotes from the value, if present
    if defined VAL set "VAL=!VAL:"=!"
    if /i "!KEY!"=="DB_HOST"     set "DB_HOST=!VAL!"
    if /i "!KEY!"=="DB_PORT"     set "DB_PORT=!VAL!"
    if /i "!KEY!"=="DB_USERNAME" set "DB_USERNAME=!VAL!"
    if /i "!KEY!"=="DB_PASSWORD" set "DB_PASSWORD=!VAL!"
    if /i "!KEY!"=="DB_DATABASE" set "DB_DATABASE=!VAL!"
)

REM ---- Build a sortable timestamp: yyyy-MM-dd_HHmmss ----------
for /f "usebackq delims=" %%i in (`powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd_HHmmss"`) do set "TIMESTAMP=%%i"

if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

set "OUTFILE=%BACKUP_DIR%\%DB_DATABASE%_%TIMESTAMP%.sql"

echo(
echo Database : %DB_DATABASE%  (host %DB_HOST%:%DB_PORT%, user %DB_USERNAME%)
echo Output   : %OUTFILE%
echo(

REM ---- Password argument (only added if a password is set) ----
set "PASS_ARG="
if defined DB_PASSWORD if not "%DB_PASSWORD%"=="" set "PASS_ARG=--password=%DB_PASSWORD%"

"%MYSQLDUMP%" ^
  --host=%DB_HOST% --port=%DB_PORT% --user=%DB_USERNAME% %PASS_ARG% ^
  --single-transaction ^
  --routines --triggers --events ^
  --default-character-set=utf8mb4 ^
  --add-drop-table ^
  "%DB_DATABASE%" > "%OUTFILE%"

if errorlevel 1 (
    echo(
    echo *** ERROR: mysqldump failed. Backup NOT created. ***
    if exist "%OUTFILE%" del "%OUTFILE%"
    exit /b 1
)

echo Backup completed successfully.

echo Removing backups older than %RETENTION_DAYS% days...
forfiles /p "%BACKUP_DIR%" /m "%DB_DATABASE%_*.sql" /d -%RETENTION_DAYS% /c "cmd /c del @path" 2>nul

echo Done.
endlocal
exit /b 0
