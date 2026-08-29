@echo off
REM CRON KURS OTOMATIS dompet-owner (pagi, setelah morning-cron)
"C:\Users\IVO\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" "C:\Users\IVO\dompet-owner\tools\fx-cron.php" >> "C:\Users\IVO\dompet-owner\tools\fx.log" 2>&1
