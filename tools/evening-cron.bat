@echo off
REM CRON MALAM dompet-owner: laporan harian 20:00
"C:\Users\IVO\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" "C:\Users\IVO\dompet-owner\tools\daily-report.php" >> "C:\Users\IVO\dompet-owner\tools\daily-report.log" 2>&1
