@echo off
setlocal
title Perpustakaan - Laragon
set "LARAGON=C:\laragon"
set "URL=http://localhost/perpustakaan/"
set "PROJECT=%~dp0."

for /d %%d in ("%LARAGON%\bin\apache\httpd-*") do set "APACHE=%%d"
for /d %%d in ("%LARAGON%\bin\mysql\mysql-*") do set "MYSQL=%%d"
if not defined APACHE (echo Apache Laragon tidak ditemukan di %LARAGON%. Ubah baris "set LARAGON=" di file ini. & pause & exit /b 1)
if not defined MYSQL (echo MySQL Laragon tidak ditemukan di %LARAGON%. & pause & exit /b 1)

rem 1. Hubungkan folder project ke www Laragon (junction, bukan salinan)
if not exist "%LARAGON%\www\perpustakaan" (
  mklink /J "%LARAGON%\www\perpustakaan" "%PROJECT%" >nul || (echo Gagal membuat link ke www Laragon. & pause & exit /b 1)
)

rem 2. Nyalakan Apache & MySQL Laragon (pakai konfigurasi Laragon sendiri) bila belum jalan
call :cek 80 || (
  echo Menyalakan Apache Laragon...
  powershell -NoProfile -Command "Start-Process '%APACHE%\bin\httpd.exe' -WorkingDirectory '%APACHE%' -WindowStyle Hidden"
)
call :cek 3306 || (
  echo Menyalakan MySQL Laragon...
  powershell -NoProfile -Command "Start-Process '%MYSQL%\bin\mysqld.exe' -ArgumentList '--defaults-file=%MYSQL%\my.ini' -WorkingDirectory '%MYSQL%' -WindowStyle Hidden"
)

rem 3. Tunggu keduanya siap (maks. 60 detik)
echo Menunggu server siap...
powershell -NoProfile -Command "function Up($p){ try { $c = New-Object Net.Sockets.TcpClient; $c.Connect('127.0.0.1', $p); $c.Close(); $true } catch { $false } }; for ($i = 0; $i -lt 60; $i++) { if ((Up 80) -and (Up 3306)) { exit 0 }; Start-Sleep 1 }; exit 1"
if errorlevel 1 (
  echo.
  echo Apache/MySQL gagal menyala. Mungkin port 80/3306 dipakai aplikasi lain ^(mis. XAMPP^) - matikan dulu, lalu coba lagi.
  pause & exit /b 1
)

rem 4. Import database saat pertama kali (bila db_perpustakaan belum ada)
"%MYSQL%\bin\mysql.exe" -u root -e "USE db_perpustakaan" 2>nul || (
  echo Mengimpor database contoh...
  "%MYSQL%\bin\mysql.exe" -u root --default-character-set=utf8mb4 < "%PROJECT%\database.sql" || (echo Import database gagal. & pause & exit /b 1)
)

rem 5. Buka website
echo Siap! Membuka %URL%
start "" "%URL%"
exit /b 0

:cek
powershell -NoProfile -Command "try { $c = New-Object Net.Sockets.TcpClient; $c.Connect('127.0.0.1', %1); $c.Close(); exit 0 } catch { exit 1 }"
exit /b %errorlevel%
