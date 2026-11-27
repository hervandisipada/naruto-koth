@echo off
title Naruto Storm KotH - XAMPP Local Launcher (PHP Native)
cd /d "%~dp0"

echo =====================================================================
echo    NARUTO STORM CONNECTIONS - KOTH (PHP NATIVE + MYSQL MODE)
echo =====================================================================
echo.
echo [INFO] Berjalan secara native dengan Apache + MySQL PHP.
echo.

tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I "httpd.exe" >NUL
if %errorlevel% neq 0 (
    if exist "C:\xampp\apache_start.bat" (
        echo [*] Menjalankan XAMPP Apache...
        start "" /min "C:\xampp\apache_start.bat"
    ) else (
        echo [PERINGATAN] Apache belum berjalan. Nyalakan Apache di XAMPP Control Panel.
    )
) else (
    echo [OK] Apache sudah berjalan.
)

tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I "mysqld.exe" >NUL
if %errorlevel% neq 0 (
    if exist "C:\xampp\mysql_start.bat" (
        echo [*] Menjalankan XAMPP MySQL...
        start "" /min "C:\xampp\mysql_start.bat"
    ) else (
        echo [PERINGATAN] MySQL belum berjalan. Nyalakan MySQL di XAMPP Control Panel.
    )
) else (
    echo [OK] MySQL sudah berjalan.
)

echo.
echo Link Lokal XAMPP:
echo   - Pendaftaran : http://localhost/naruto-koth/
echo   - Panel Admin : http://localhost/naruto-koth/admin
echo   - Live Overlay: http://localhost/naruto-koth/overlay.php
echo.

start "" "http://localhost/naruto-koth/admin"
pause
