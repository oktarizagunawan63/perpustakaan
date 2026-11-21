@echo off
setlocal
title Perpustakaan Admin - Port 8080
set "LARAGON=C:\laragon"
set "URL=http://localhost:8080"
set "PROJECT=%~dp0."
set "PORT=8080"

for /d %%d in ("%LARAGON%\bin\php\php-*") do set "PHP=%%d"
for /d %%d in ("%LARAGON%\bin\mysql\mysql-*") do set "MYSQL=%%d"
if not defined PHP (echo PHP Laragon tidak ditemukan di %LARAGON%. & pause & exit /b 1)
if not defined MYSQL (echo MySQL Laragon tidak ditemukan di %LARAGON%. & pause & exit /b 1)

rem 1. Skip - tidak perlu link ke www

rem 2. Nyalakan MySQL bila belum jalan
call :cek 3306 || (
  echo Menyalakan MySQL Laragon...
  powershell -NoProfile -Command "Start-Process '%MYSQL%\bin\mysqld.exe' -ArgumentList '--defaults-file=%MYSQL%\my.ini' -WorkingDirectory '%MYSQL%' -WindowStyle Hidden"
)

rem 3. Import database saat pertama kali
"%MYSQL%\bin\mysql.exe" -u root -e "USE db_perpustakaan" 2>nul || (
  echo Mengimpor database contoh...
  "%MYSQL%\bin\mysql.exe" -u root --default-character-set=utf8mb4 < "%PROJECT%\database.sql" || (echo Import database gagal. & pause & exit /b 1)
)

rem 4. Tunggu MySQL siap
echo Menunggu MySQL siap...
powershell -NoProfile -Command "for ($i = 0; $i -lt 30; $i++) { try { $c = New-Object Net.Sockets.TcpClient; $c.Connect('127.0.0.1', 3306); $c.Close(); exit 0 } catch { Start-Sleep 1 } }; exit 1"
if errorlevel 1 (echo MySQL gagal menyala. & pause & exit /b 1)

rem 5. Jalankan PHP Built-in Server untuk Admin di port 8080
echo.
echo ============================================
echo  SERVER ADMIN - Port %PORT%
echo ============================================
echo  URL: %URL%
echo  Tekan Ctrl+C untuk menghentikan server
echo ============================================
echo.
start "" "%URL%"
cd "%PROJECT%"
php -S localhost:%PORT% -t .
exit /b 0

:cek
powershell -NoProfile -Command "try { $c = New-Object Net.Sockets.TcpClient; $c.Connect('127.0.0.1', %1); $c.Close(); exit 0 } catch { exit 1 }"
exit /b %errorlevel%
