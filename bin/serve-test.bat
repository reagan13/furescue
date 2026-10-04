@echo off
setlocal
cd /d "%~dp0\.."
set FURESCUE_ENV_FILE=.env.test
set APP_ENV=testing
set APP_TESTING=1
php bin\serve-test.php %*
