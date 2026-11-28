@echo off
chcp 65001 >nul
title Naruto Storm KotH - TikTok Live Auto-Queue Bot
color 0A

echo ================================================================
echo     🍃 NARUTO STORM KOTH - TIKTOK LIVE AUTO-QUEUE BOT 🍃
echo ================================================================
echo   Host Default  : @herzg1209
echo   API Server    : http://localhost/naruto-koth/api/live-request.php
echo   Room Key      : hervandisipada
echo ================================================================
echo.

set HOST_NAME=herzg1209
set /p USER_INPUT="Masukkan username TikTok host (Tekan ENTER untuk default @%HOST_NAME%): "
if not "%USER_INPUT%"=="" set HOST_NAME=%USER_INPUT%

echo.
echo Menghubungkan ke live @%HOST_NAME%...
echo Tekan Ctrl + C untuk menghentikan bot.
echo.

python bot\tiktok_live_bot.py --host %HOST_NAME%

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo [ERROR] Terjadi kesalahan saat menjalankan bot.
    pause
)
