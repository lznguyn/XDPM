@echo off
echo ========================================
echo Starting Service-1 (Auth Service)
echo ========================================
echo.

cd service-1

echo Checking .NET SDK...
dotnet --version
if %errorlevel% neq 0 (
    echo ERROR: .NET SDK not found!
    echo Please install .NET SDK 9.0 or later
    pause
    exit /b 1
)

echo.
echo Restoring dependencies...
dotnet restore

echo.
echo Building project...
dotnet build

echo.
echo ========================================
echo Starting Service-1...
echo Service will run at: http://localhost:5200
echo Swagger UI: http://localhost:5200/swagger
echo Health Check: http://localhost:5200/health
echo ========================================
echo.
echo Press Ctrl+C to stop the service
echo.

dotnet run

pause

