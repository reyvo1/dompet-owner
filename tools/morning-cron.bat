@echo off
REM CRON HARIAN dompet-owner (pagi 06:00)
"C:\Users\IVO\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" "C:\Users\IVO\dompet-owner\tools\bill-notify.php" >> "C:\Users\IVO\dompet-owner\tools\bill-notify.log" 2>&1
"C:\Users\IVO\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" "C:\Users\IVO\dompet-owner\tools\morning-ai-cron.php" >> "C:\Users\IVO\dompet-owner\tools\reminder.log" 2>&1
"C:\Users\IVO\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" "C:\Users\IVO\dompet-owner\tools\rec-cron.php"
