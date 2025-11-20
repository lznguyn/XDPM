@echo off
echo ========================================
echo Starting PHP Server for MuTraPro Web...
echo ========================================
echo.

cd service-1\Web

echo Checking PHP installation...
php --version >nul 2>&1
if %errorlevel% neq 0 (
    echo.
    echo ❌ ERROR: PHP is not installed or not in PATH!
    echo.
    echo Please install PHP from: https://www.php.net/downloads.php
    echo Or use XAMPP/WAMP which includes PHP.
    echo.
    pause
    exit /b 1
)

echo.
echo ✅ PHP found!
echo.
echo Starting PHP development server...
echo.
echo 📁 Serving from: %CD%
echo 🌐 Server running at: http://localhost:8082
echo 🔐 Login page: http://localhost:8082/login.php
echo 📝 Register page: http://localhost:8082/register.php
echo 👤 Admin Panel: http://localhost:8082/admin/admin_page.php
echo.
echo Press Ctrl+C to stop the server.
echo.

php -S localhost:8082

pause

