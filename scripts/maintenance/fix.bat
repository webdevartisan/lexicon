@echo off
REM Auto-fix code issues
REM Usage: scripts\fix.bat

echo ======================================
echo   Auto-fixing Code Issues
echo ======================================
echo.

echo 1. Fixing code style with Pint...
call vendor\bin\pint
echo [OK] Code style fixed
echo.

echo 2. Clearing caches...
if exist .phpunit.cache rmdir /s /q .phpunit.cache
if exist var\cache rmdir /s /q var\cache
echo [OK] Caches cleared
echo.

echo 3. Regenerating autoload...
call composer dump-autoload
echo [OK] Autoload regenerated
echo.

echo ======================================
echo   Fixes applied!
echo ======================================
echo.
echo Run 'scripts\test.bat' to verify
