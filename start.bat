@echo off
cd /d "%~dp0"
start "Server Perpustakaan" "C:\laragon\bin\php\php-8.3.26-Win32-vs16-x64\php.exe" -S localhost:8080
start http://localhost:8080
