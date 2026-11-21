@echo off
setlocal
title Perpustakaan User - Port 8081
set "LARAGON=C:\laragon"
set "URL=http://localhost:8081"
set "PROJECT=%~dp0."
set "PORT=8081"

for /d %%d in ("%LARAGON%\bin\php\php-*") do set "PHP=%%d"
for /d %%d in ("%LARAGON%\bin\mysql\mysql-*") do set "MYSQL=%%d"
if not defined PHP (echo PHP Laragon tidak ditemukan di %LARAGON%. & pause & exit /b 1)
if not defined MYSQL (echo MySQL Laragon tidak ditemukan di %LARAGON%. & pause & exit /b 1)

rem 1. Cek MySQL sudah jalan
call :cek 3306 || (
  echo MySQL belum jalan. Jalankan server admin dulu!
  pause
  exit /b 1
)

rem 2. Jalankan PHP Built-in Server untuk User di port 8081
echo.
echo ============================================
echo  SERVER USER - Port %PORT%
echo ============================================
echo  URL: %URL%
echo  Tekan Ctrl+C untuk menghentikan server
echo ============================================
echo.
start "" "%URL%"
cd "%PROJECT%"
"%PHP%\php.exe" -S localhost:%PORT% -t .
exit /b 0

:cek
powershell -NoProfile -Command "try { $c = New-Object Net.Sockets.TcpClient; $c.Connect('127.0.0.1', %1); $c.Close(); exit 0 } catch { exit 1 }"
exit /b %errorlevel%
