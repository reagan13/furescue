@echo off
setlocal
cd /d "%~dp0\.."
php bin\reset-test-db.php
