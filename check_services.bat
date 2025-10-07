@echo off
echo Checking Laragon services...
echo.

echo MySQL Service:
sc query mysql | findstr /i "STATE"
echo.

echo PHP Version:
where php
echo.

php -v
echo.

echo MySQL Process:
tasklist /fi "imagename eq mysqld.exe"
echo.

echo Laragon Process:
tasklist /fi "imagename eq laragon.exe"
echo.

echo Apache Process:
tasklist /fi "imagename eq httpd.exe"
echo.

echo Press any key to continue...
pause > nul
