@echo off
cls
cd..
IF EXIST "c:\php\php.exe" (
  echo Writing Mech Tags	
    c:\php\php.exe --no-php-ini -d memory_limit=4096M .\php\writetag.php
) ELSE (
  echo You should have a php 7 install in c:\php. Get it from https://windows.php.net/download/ and extract to c:\php
)
pause
