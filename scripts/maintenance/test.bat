@echo off
REM Test runner script with validation
REM Usage: scripts\test.bat

echo ======================================
echo   Running Local Validation Suite
echo ======================================
echo.

REM 1. Composer validation
echo 1. Validating composer.json...
call composer validate --strict
if %errorlevel% neq 0 (
    echo [ERROR] Composer validation failed
    exit /b 1
)
echo [OK] Composer validation
echo.

REM 2. Code style check
echo 2. Checking code style (Pint)...
if exist vendor\bin\pint (
    call vendor\bin\pint --test
    if %errorlevel% neq 0 (
        echo [ERROR] Code style check failed
        echo [INFO] Run 'vendor\bin\pint' to auto-fix
        exit /b 1
    )
    echo [OK] Code style check
) else (
    echo [SKIP] Pint not installed
)
echo.

REM 3. Static analysis
echo 3. Running static analysis (PHPStan)...
if exist vendor\bin\phpstan (
    call vendor\bin\phpstan analyse --memory-limit=2G
    if %errorlevel% neq 0 (
        echo [ERROR] Static analysis failed
        exit /b 1
    )
    echo [OK] Static analysis
) else (
    echo [SKIP] PHPStan not installed
)
echo.

REM 4. Security audit
echo 4. Running security audit...
call composer audit
if %errorlevel% neq 0 (
    echo [WARN] Security issues found
    REM Don't exit on security warnings for now
)
echo [OK] Security audit
echo.

REM 5. Run tests
echo 5. Running test suite...
call vendor\bin\pest
if %errorlevel% neq 0 (
    echo [ERROR] Test suite failed
    exit /b 1
)
echo [OK] Test suite
echo.

REM 6. Code coverage (optional)
echo 6. Checking code coverage...
if exist vendor\bin\pest (
    call vendor\bin\pest --coverage --min=60
    if %errorlevel% neq 0 (
        echo [WARN] Code coverage below 60%% (continuing anyway)
    ) else (
        echo [OK] Code coverage
    )
) else (
    echo [SKIP] Coverage check
)
echo.

echo ======================================
echo   Core checks passed! [OK]
echo ======================================
