@echo off
rem Mematikan Apache & MySQL milik Laragon (hanya proses dari folder C:\laragon)
powershell -NoProfile -Command "Get-CimInstance Win32_Process | Where-Object { $_.Name -in 'httpd.exe','mysqld.exe' -and $_.ExecutablePath -like 'C:\laragon\*' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force; Write-Host ('Berhenti: ' + $_.Name) }"
echo Server Laragon sudah dimatikan.
timeout /t 2 >nul
