@echo off
REM Pre-deployment validation
REM Usage: scripts\deploy-check.bat

echo ======================================
echo   Pre-Deployment Checks
echo ======================================
echo.

echo Checking environment...
if not exist .env (
    echo [ERROR] .env file missing
    exit /b 1
)
echo [OK] Environment file exists
echo.

echo Running full test suite...
call scripts\test.bat
if %errorlevel% neq 0 (
    echo [ERROR] Tests failed - deployment blocked
    exit /b 1
)
echo.

echo Checking production dependencies...
call composer install --no-dev --optimize-autoloader
if %errorlevel% neq 0 (
    echo [ERROR] Production dependencies failed
    exit /b 1
)
echo.

echo ======================================
echo   Ready for deployment! [OK]
echo ======================================
