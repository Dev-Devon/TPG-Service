@echo off
echo Starting PHP server on http://localhost:8000
cd /d "%~dp0"
php -S localhost:8000 -t . 
pause