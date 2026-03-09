::[Bat To Exe Converter]
::
::YAwzoRdxOk+EWAjk
::fBw5plQjdCyDJGyX8VAjFB9VXg+AAE+1BaAR7ebv/Nagq1kiZucvd7Pv6pPOE+UB/EzncKo603hJkd8JMB1ZaBuoYQEL+CBLtWvl
::YAwzuBVtJxjWCl3EqQJgSA==
::ZR4luwNxJguZRRnk
::Yhs/ulQjdF+5
::cxAkpRVqdFKZSDk=
::cBs/ulQjdF+5
::ZR41oxFsdFKZSDk=
::eBoioBt6dFKZSDk=
::cRo6pxp7LAbNWATEpCI=
::egkzugNsPRvcWATEpCI=
::dAsiuh18IRvcCxnZtBJQ
::cRYluBh/LU+EWAnk
::YxY4rhs+aU+JeA==
::cxY6rQJ7JhzQF1fEqQJQ
::ZQ05rAF9IBncCkqN+0xwdVs0
::ZQ05rAF9IAHYFVzEqQJQ
::eg0/rx1wNQPfEVWB+kM9LVsJDGQ=
::fBEirQZwNQPfEVWB+kM9LVsJDGQ=
::cRolqwZ3JBvQF1fEqQJQ
::dhA7uBVwLU+EWDk=
::YQ03rBFzNR3SWATElA==
::dhAmsQZ3MwfNWATElA==
::ZQ0/vhVqMQ3MEVWAtB9wSA==
::Zg8zqx1/OA3MEVWAtB9wSA==
::dhA7pRFwIByZRRnk
::Zh4grVQjdCyDJGyX8VAjFB9VXg+AAE+1BaAR7ebv/Nagq1kiZucvd7Pv6pPOE+UB/EzncKo603hJkd8JMC9oWVqdPUZ6rHZH1g==
::YB416Ek+ZG8=
::
::
::978f952a14a936cc963da21a135fa983
@echo off
setlocal EnableDelayedExpansion

REM --- Config ---
set PORT=8000
set WEBSITE_FOLDER=website
set "WEBSITE_ROOT=%~dp0%WEBSITE_FOLDER%"
set "PHP_EXEC=%~dp0bin\php.exe"

REM --- Check PHP exists ---
if not exist "%PHP_EXEC%" (
    where php >nul 2>&1
    if not errorlevel 1 (
        set "PHP_EXEC=php"
    ) else (
        echo ERROR: PHP executable not found in bin or PATH.
        pause
        exit /b
    )
)

REM --- Check if PHP server is already running ---
set SERVER_RUNNING=0
for /f "tokens=5" %%a in ('netstat -ano ^| findstr :%PORT%') do (
    tasklist /fi "PID eq %%a" | find /I "php.exe" >nul
    if not errorlevel 1 (
        set SERVER_RUNNING=1
        goto FOUND_SERVER
    )
)
:FOUND_SERVER

if %SERVER_RUNNING%==1 (
    echo PHP server already running on port %PORT%.
) else (
    echo Starting PHP server on port %PORT%...
    start /MIN "" "%PHP_EXEC%" -S localhost:%PORT% -t "%WEBSITE_ROOT%"
    REM --- Wait a moment for the server to start ---
    timeout /t 2 >nul
)

REM --- Open the browser ---
start "" "http://localhost:%PORT%/index.html"