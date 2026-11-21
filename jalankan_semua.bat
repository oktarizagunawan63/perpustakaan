@echo off
title Perpustakaan - Jalankan Semua
set "LARAGON=C:\laragon"
set "PROJECT=%~dp0."

for /d %%d in ("%LARAGON%\bin\php\php-*") do set "PHP=%%d"
for /d %%d in ("%LARAGON%\bin\mysql\mysql-*") do set "MYSQL=%%d"

if not defined PHP (echo PHP tidak ditemukan di %LARAGON%. & pause & exit /b 1)
if not defined MYSQL (echo MySQL tidak ditemukan di %LARAGON%. & pause & exit /b 1)

echo ============================================
echo  PERPUSTAKAAN - STARTING...
echo ============================================
echo.

rem 1. Cek dan nyalakan MySQL jika belum jalan
echo [1/5] Cek MySQL...
powershell -NoProfile -Command "try { $c = New-Object Net.Sockets.TcpClient; $c.Connect('127.0.0.1', 3306); $c.Close(); Write-Host 'MySQL sudah jalan' -ForegroundColor Green; exit 0 } catch { exit 1 }"
if errorlevel 1 (
  echo MySQL belum jalan, menyalakan...
  powershell -NoProfile -Command "Start-Process '%MYSQL%\bin\mysqld.exe' -ArgumentList '--defaults-file=%MYSQL%\my.ini' -WorkingDirectory '%MYSQL%' -WindowStyle Hidden"
  timeout /t 5 /nobreak >nul
)

rem 2. Import database jika belum ada
echo [2/5] Cek database...
"%MYSQL%\bin\mysql.exe" -u root -e "USE db_perpustakaan" 2>nul
if errorlevel 1 (
  echo Import database...
  "%MYSQL%\bin\mysql.exe" -u root --default-character-set=utf8mb4 < "%PROJECT%\database.sql"
  if errorlevel 1 (echo Import gagal! & pause & exit /b 1)
  echo Database berhasil diimport!
) else (
  echo Database sudah ada
)

rem 3. Jalankan server admin di port 9000
echo [3/5] Start server ADMIN (port 9000)...
start "Server Admin - Port 9000" "%PHP%\php.exe" -S localhost:9000 -t "%PROJECT%"

rem 4. Jalankan server anggota di port 9001
echo [4/5] Start server ANGGOTA (port 9001)...
start "Server Anggota - Port 9001" "%PHP%\php.exe" -S localhost:9001 -t "%PROJECT%"

rem 5. Buka browser
timeout /t 2 /nobreak >nul
echo [5/5] Membuka browser...
start http://localhost:9000/login.php
start http://localhost:9001/login.php

echo.
echo ============================================
echo  SEMUA SIAP!
echo ============================================
echo  Admin:   http://localhost:9000
echo  Anggota: http://localhost:9001
echo ============================================
echo.
echo Tekan tombol apapun untuk menutup window ini
echo (Server tetap jalan di background)
pause >nul
