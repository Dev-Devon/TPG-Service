::[Bat To Exe Converter]
::
::YAwzoRdxOk+EWAjk
::fBw5plQjdCyDJGyX8VAjFB9VXg+AAE+1BaAR7ebv/Nagq1kiZucvd7Pv6pPOE+UB/EzncKo603hJkd8JMB1ZaBuoYQEL+CBLtWvl
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
::Zh4grVQjdCyDJGyX8VAjFB9VXg+AAE+1BaAR7ebv/Nagq1kiZucvd7Pv6pPOE+UB/EzncKo603hJkd8JMC9oWVyudgpU
::YB416Ek+ZG8=
::
::
::978f952a14a936cc963da21a135fa983
@echo off
setlocal enabledelayedexpansion
title TPG Service

:: Set variables
set "APP_NAME=TPG"
set "PORT=8000"
set "SERVER_ADDRESS=localhost:%PORT%"
set "SCRIPT_DIR=%~dp0"
set "WEBSITE_ROOT=%SCRIPT_DIR%"
set "PHP_EXE=%SCRIPT_DIR%bin\php.exe"
set "LOG_FILE=%TEMP%\%APP_NAME%.log"

:: Kill existing TPG.exe process
:CHECK_AND_KILL
tasklist /FI "IMAGENAME eq TPG.exe" 2>NUL | find /I "TPG.exe" >NUL
if not errorlevel 1 (
    echo %date% %time% - Found TPG.exe, killing it... >> "%LOG_FILE%"
    taskkill /F /IM TPG.exe >NUL 2>&1
    timeout /t 2 /nobreak >NUL
    goto CHECK_AND_KILL
)

:: Kill existing TPG.exe process
:CHECK_AND_KILL
tasklist /FI "IMAGENAME eq TPG.exe" 2>NUL | find /I "CLI.exe" >NUL
if not errorlevel 1 (
    echo %date% %time% - Found CLI.exe, killing it... >> "%LOG_FILE%"
    taskkill /F /IM CLI.exe >NUL 2>&1
    timeout /t 2 /nobreak >NUL
    goto CHECK_AND_KILL
)

:: Check if PHP exists
if not exist "%PHP_EXE%" (
    echo ERROR: PHP executable not found at %PHP_EXE%
    echo %date% %time% - ERROR: PHP not found >> "%LOG_FILE%"
    echo Press any key to exit...
    pause >NUL
    exit /b 1
)

:: Check if index.html exists
if not exist "%WEBSITE_ROOT%index.html" (
    echo ERROR: index.html not found in %WEBSITE_ROOT%
    echo %date% %time% - ERROR: index.html not found >> "%LOG_FILE%"
    echo Press any key to exit...
    pause >NUL
    exit /b 1
)

:: Find available port
:CHECK_PORT
netstat -ano 2>NUL | find ":%PORT%" >NUL 2>&1
if errorlevel 1 (
    set "SERVER_ADDRESS=localhost:%PORT%"
    goto START_SERVER
) else (
    set /a PORT+=1
    goto CHECK_PORT
)

:START_SERVER
echo %date% %time% - Starting server on http://%SERVER_ADDRESS% >> "%LOG_FILE%"
echo Starting PHP server on http://%SERVER_ADDRESS%
echo Application running from: %WEBSITE_ROOT%
echo.

:: Start PHP server in background (FIXED - removed /B and used START properly)
start /B "" "%PHP_EXE%" -S %SERVER_ADDRESS% -t "%WEBSITE_ROOT%"

:: Wait for server to start
timeout /t 3 /nobreak >NUL

:: Open browser
start "" "http://%SERVER_ADDRESS%"

:: Start monitoring process (this runs in the same window)
echo Server is running. Monitoring...
echo.
echo [Press Ctrl+C to stop the server and exit]

:MONITOR_LOOP
:: Check every 1 minute if server is running
timeout /t 60 /nobreak >NUL

:: Check if PHP server process is still running
tasklist /FI "IMAGENAME eq php.exe" 2>NUL | find /I "php.exe" >NUL
if errorlevel 1 (
    echo %date% %time% - PHP server stopped unexpectedly >> "%LOG_FILE%"
    echo.
    echo PHP server has stopped unexpectedly.
    echo Closing application...
    timeout /t 2 /nobreak >NUL
    
    :: Kill any TPG.exe
    taskkill /F /IM TPG.exe >NUL 2>&1
    timeout /t 1 /nobreak >NUL
    
    :: Kill any remaining php.exe
    taskkill /F /IM php.exe >NUL 2>&1
    
    echo %date% %time% - Application closed >> "%LOG_FILE%"
    exit
)

:: Check if TPG.exe is still running (if it restarted)
tasklist /FI "IMAGENAME eq TPG.exe" 2>NUL | find /I "TPG.exe" >NUL
if not errorlevel 1 (
    echo %date% %time% - Found new TPG.exe instance >> "%LOG_FILE%"
    echo Found TPG.exe running. Restarting server...
    taskkill /F /IM TPG.exe >NUL 2>&1
    taskkill /F /IM php.exe >NUL 2>&1
    timeout /t 2 /nobreak >NUL
    
    :: Restart server (FIXED)
    start /B "" "%PHP_EXE%" -S %SERVER_ADDRESS% -t "%WEBSITE_ROOT%"
    timeout /t 2 /nobreak >NUL
)

goto MONITOR_LOOP