@echo off
REM ============================================================
REM  Vantage MySQL backup script (Laragon / Windows)
REM  Creates a timestamped, compressed-friendly .sql dump.
REM  Designed to be run standalone OR as a Cobian Reflector
REM  "pre-backup" event so the fresh dump gets picked up.
REM ============================================================

REM ---- Configuration (edit these if your setup changes) ------
set "MYSQLDUMP=C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe"
set "DB_HOST=127.0.0.1"
set "DB_PORT=3306"
set "DB_USER=root"
set "DB_PASS="
set "DB_NAME=vantage"
set "BACKUP_DIR=C:\Backups\vantage-db"
set "RETENTION_DAYS=30"
REM ------------------------------------------------------------

REM ---- Build a sortable timestamp: yyyy-MM-dd_HHmmss ----------
for /f "usebackq delims=" %%i in (`powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd_HHmmss"`) do set "TIMESTAMP=%%i"

REM ---- Ensure the backup folder exists ------------------------
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

set "OUTFILE=%BACKUP_DIR%\%DB_NAME%_%TIMESTAMP%.sql"

echo(
echo Backing up database "%DB_NAME%" to:
echo   %OUTFILE%
echo(

REM ---- Password argument (only added if DB_PASS is set) -------
set "PASS_ARG="
if defined DB_PASS if not "%DB_PASS%"=="" set "PASS_ARG=--password=%DB_PASS%"

REM ---- Run the dump ------------------------------------------
REM  --single-transaction : consistent snapshot without locking (InnoDB)
REM  --routines --triggers --events : include stored procs, triggers, events
REM  --default-character-set=utf8mb4 : preserve full unicode
"%MYSQLDUMP%" ^
  --host=%DB_HOST% --port=%DB_PORT% --user=%DB_USER% %PASS_ARG% ^
  --single-transaction ^
  --routines --triggers --events ^
  --default-character-set=utf8mb4 ^
  --add-drop-table ^
  "%DB_NAME%" > "%OUTFILE%"

if errorlevel 1 (
    echo(
    echo *** ERROR: mysqldump failed. Backup NOT created. ***
    REM Remove the empty/partial file so it isn't mistaken for a good backup
    if exist "%OUTFILE%" del "%OUTFILE%"
    exit /b 1
)

echo Backup completed successfully.

REM ---- Retention: delete dumps older than RETENTION_DAYS ------
echo Removing backups older than %RETENTION_DAYS% days...
forfiles /p "%BACKUP_DIR%" /m "%DB_NAME%_*.sql" /d -%RETENTION_DAYS% /c "cmd /c del @path" 2>nul

echo Done.
exit /b 0
