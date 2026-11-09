@echo off
title Naruto Storm KotH - Local Server
cd /d "%~dp0"

echo =====================================================================
echo       NARUTO STORM CONNECTIONS - KING OF THE HILL LIVE SERVER
echo =====================================================================
echo.

set "PY_EXE="
if exist "%LOCALAPPDATA%\Programs\Python\Python311\python.exe" (
    set "PY_EXE=%LOCALAPPDATA%\Programs\Python\Python311\python.exe"
) else (
    where py >nul 2>nul
    if %errorlevel% equ 0 (
        set "PY_EXE=py"
    ) else (
        where python >nul 2>nul
        if %errorlevel% equ 0 (
            set "PY_EXE=python"
        )
    )
)

if "%PY_EXE%"=="" (
    echo [ERROR] Python tidak ditemukan di komputer ini!
    echo Silakan install Python terlebih dahulu.
    echo.
    pause
    exit /b 1
)

echo Menggunakan Python: %PY_EXE%
echo.

"%PY_EXE%" server.py

if %errorlevel% neq 0 (
    echo.
    echo [ERROR] Server berhenti dengan kode error: %errorlevel%
    echo.
)

pause
