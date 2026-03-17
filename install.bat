@echo off
setlocal enabledelayedexpansion


echo.
echo ===================================
echo Lexicon Blog Installation
echo ===================================
echo.


:: Store the script directory as the project root.
set "PROJECT_ROOT=%~dp0"
cd /d "%PROJECT_ROOT%"


:: ============================================================================
:: STEP 1: Check System Requirements
:: ============================================================================


echo [Step 1/5] Checking system requirements...
echo.


:: Verify PHP is installed for Composer and framework execution.
where php >nul 2>nul
if not "%ERRORLEVEL%"=="0" (
    echo [ERROR] PHP is not installed
    echo         Install from: https://windows.php.net/download/
    pause
    exit /b 1
)


:: Capture the PHP version string for version‑check later.
for /f "tokens=*" %%i in ('php -r "echo PHP_VERSION;"') do set PHP_VERSION=%%i
echo [OK] PHP: %PHP_VERSION%

:: Assume Node.js is unavailable until proven otherwise.
set NODE_INSTALLED=false
where node >nul 2>nul
if "%ERRORLEVEL%"=="0" (
    :: Capture Node.js version if present.
    for /f "tokens=*" %%i in ('node --version 2^>nul') do set NODE_VERSION=%%i
    echo [OK] Node.js: !NODE_VERSION!
    set NODE_INSTALLED=true

    :: Check npm as well, since it’s required for build scripts.
    where npm >nul 2>nul
    if "%ERRORLEVEL%"=="0" (
        :: Capture npm version if present.
        for /f "tokens=*" %%i in ('npm --version 2^>nul') do set NPM_VERSION=%%i
        echo [OK] npm: v!NPM_VERSION!
    )
) else (
    :: Node missing; icon rendering will fall back to client‑side.
    echo [WARNING] Node.js is not installed
    echo           Server-side icon rendering will be disabled
    echo           Download from: https://nodejs.org/
)


echo.


:: ============================================================================
:: STEP 2: Install Composer Dependencies
:: ============================================================================


echo [Step 2/5] Installing PHP dependencies...
echo.


:: Ensure Composer is available before installing PHP packages.
where composer >nul 2>nul
if not "%ERRORLEVEL%"=="0" (
    echo [ERROR] Composer is not installed
    echo         Install from: https://getcomposer.org/download/
    pause
    exit /b 1
)


:: Run production‑only, optimized install with no interaction.
call composer install --no-dev --optimize-autoloader --no-interaction
if not "%ERRORLEVEL%"=="0" (
    echo.
    echo [ERROR] Composer install failed
    pause
    exit /b 1
)


echo.
echo [OK] PHP dependencies installed
echo.


:: ============================================================================
:: STEP 3: Install Node.js Dependencies
:: ============================================================================


if "!NODE_INSTALLED!"=="true" (
    echo [Step 3/5] Installing Node.js dependencies...
    echo.

    :: Install Node modules for build and icon rendering.
    call npm install

    if not "%ERRORLEVEL%"=="0" (
        echo.
        echo [WARNING] npm install failed
        echo           You may need to run 'npm install' manually
        echo.
    ) else (
        echo.
        echo [OK] Node.js dependencies installed
        echo.
    )
) else (
    :: Skip Node dependencies when Node is not installed.
    echo [Step 3/5] Skipping Node.js dependencies (Node.js not installed)
    echo.
)


:: ============================================================================
:: STEP 4: Create Directories
:: ============================================================================


echo [Step 4/5] Creating required directories...


:: Ensure storage subdirectories exist for logs, cache, and uploads.
if not exist "storage\cache" mkdir "storage\cache"
if not exist "storage\logs" mkdir "storage\logs"
if not exist "storage\uploads" mkdir "storage\uploads"


echo [OK] Directories created
echo.


:: ============================================================================
:: STEP 5: Run Application Setup
:: ============================================================================


echo [Step 5/5] Running application setup...
echo.


:: Attempt to locate setup.php in likely locations.
if exist "setup.php" (
    php setup.php
) else if exist "scripts\setup\setup.php" (
    php scripts\setup\setup.php
) else (
    echo [ERROR] setup.php not found in root or scripts folder
    pause
    exit /b 1
)


if not "%ERRORLEVEL%"=="0" (
    echo.
    echo [ERROR] Setup failed
    pause
    exit /b 1
)


:: ============================================================================
:: FINAL SUMMARY
:: ============================================================================


echo.
echo ===================================
echo Installation Complete!
echo ===================================
echo.
echo [OK] All dependencies installed
echo [OK] Application configured
echo.
echo Next steps:
echo    1. Configure your web server (Apache/Nginx)
echo    2. Point document root to: !PROJECT_ROOT!public
echo    3. Restart your web server
echo    4. Visit your site!
echo.


if "!NODE_INSTALLED!"=="false" (
    :: Icon rendering is disabled when Node.js is not available.
    echo [WARNING] Icon rendering is disabled (Node.js not installed)
    echo           To enable: Install Node.js, then run: npm install
    echo.
)


echo.
echo Installation completed successfully!
echo.
pause >nul
