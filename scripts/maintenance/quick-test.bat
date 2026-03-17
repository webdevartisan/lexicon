@echo off
REM Quick test without coverage
REM Usage: scripts\quick-test.bat

echo Running quick tests...
echo.

call vendor\bin\pint --test
if %errorlevel% neq 0 exit /b 1

call vendor\bin\pest
if %errorlevel% neq 0 exit /b 1

echo.
echo [OK] Quick tests passed!
