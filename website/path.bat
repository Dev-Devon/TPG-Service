@echo off

REM Get root folder where the bat file exists
set "ROOT=%~dp0"

REM Remove trailing backslash
set "ROOT=%ROOT:~0,-1%"

REM Target path
set "BIN_PATH=%ROOT%\website\bin"

REM Add permanently to user PATH
setx PATH "%PATH%;%BIN_PATH%"

echo.
echo Added permanently to PATH:
echo %BIN_PATH%
echo.
echo Open a new terminal for the change to take effect.
pause