::[Bat To Exe Converter]
::
::YAwzoRdxOk+EWAjk
::fBw5plQjdCuDJGmW+0UiKRZZRQqFAFuoCb8Z6/zHx96qjnEtRu01fYzP5eayN+UR+UzwcKor12xTm8QCM0oWdxGkDg==
::YAwzuBVtJxjWCl3EqQJgSA==
::ZR4luwNxJguZRRnk
::Yhs/ulQjdF+5
::cxAkpRVqdFKZSzk=
::cBs/ulQjdF+5
::ZR41oxFsdFKZSDk=
::eBoioBt6dFKZSDk=
::cRo6pxp7LAbNWATEpCI=
::egkzugNsPRvcWATEpCI=
::dAsiuh18IRvcCxnZtBJQ
::cRYluBh/LU+EWAnk
::YxY4rhs+aU+IeA==
::cxY6rQJ7JhzQF1fEqQImeXs=
::ZQ05rAF9IBncCkqN+0xwdVtCHWQ=
::ZQ05rAF9IAHYFVzEqQIEGDwUeAuNMHja
::eg0/rx1wNQPfEVWB+kM9LVsJDGQ=
::fBEirQZwNQPfEVWB+kM9LVsJDGQ=
::cRolqwZ3JBvQF1fEqQJQ
::dhA7uBVwLU+EWH2B4k0+SA==
::YQ03rBFzNR3SWATElA==
::dhAmsQZ3MwfNWATElA==
::ZQ0/vhVqMQ3MEVWAtB9wSA==
::Zg8zqx1/OA3MEVWAtB9wSA==
::dhA7pRFwIByZRRnk
::Zh4grVQjdCuDJGmW+0UiKRZZRQqFAFuoCb8Z6/zHx96qjnEtRu01fYzP5eayFNA0onO3PNgozn86
::YB416Ek+ZG8=
::
::
::978f952a14a936cc963da21a135fa983
@echo off
setlocal

set PORT=8000
set WEBSITE_FOLDER=website
set SERVER_ADDRESS=localhost:%PORT%
set "WEBSITE_ROOT=%~dp0%WEBSITE_FOLDER%"
set "PHP_EXEC="

:: Check for local/portable PHP first
set "PHP_EXEC_LOCAL=%~dp0bin\php.exe"
if exist "%PHP_EXEC_LOCAL%" (
    set "PHP_EXEC=%PHP_EXEC_LOCAL%"
    goto START_SERVER
)

:: Fallback to system PHP in PATH
where php >nul 2>&1
if not errorlevel 1 (
    set "PHP_EXEC=php"
    goto START_SERVER
)

:: Error if PHP is not found
echo ERROR: PHP executable not found.
pause
goto :EOF

:START_SERVER
if not exist "%WEBSITE_ROOT%" (
    echo ERROR: Website folder '%WEBSITE_FOLDER%' not found.
    pause
    goto :EOF
)

echo Starting PHP server on http://%SERVER_ADDRESS%
echo Press Ctrl+C to stop.
echo.

start "" http://%SERVER_ADDRESS%
timeout /t 1 /nobreak >nul

"%PHP_EXEC%" -S %SERVER_ADDRESS% -t "%WEBSITE_ROOT%"

echo Server stopped.
pause

endlocal