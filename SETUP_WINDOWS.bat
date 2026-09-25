@echo off
title Setup Sistem Arsip Prodi
echo ========================================
echo   SETUP SISTEM ARSIP PRODI - LARAVEL 10
echo ========================================
echo.
if not exist .env copy .env.example .env
call composer install
if errorlevel 1 goto error
call php artisan key:generate
if errorlevel 1 goto error
call php artisan migrate --seed
if errorlevel 1 goto error
echo.
echo Setup selesai. Server dijalankan di http://127.0.0.1:8000
call php artisan serve
exit /b 0

:error
echo.
echo Setup gagal. Pastikan PHP 8.1, Composer, dan database arsip_prodi sudah aktif.
pause
exit /b 1
