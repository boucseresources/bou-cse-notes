@echo off
set "BOU_APP=%~dp0.."
schtasks /Create /TN "BOU-CSE-Temporary-Cleanup" /SC MINUTE /MO 5 /TR "C:\xampp\php\php.exe \"%BOU_APP%\bin\temp-cleanup.php\"" /F
pause
