@echo off
echo ========================================
echo Starting Service-1 via Docker
echo ========================================
echo.

echo Checking Docker...
docker --version
if %errorlevel% neq 0 (
    echo ERROR: Docker not found!
    echo Please install Docker Desktop
    pause
    exit /b 1
)

echo.
echo Starting MySQL (if not running)...
docker-compose up -d mysql

echo.
echo Waiting for MySQL to be ready...
timeout /t 5 /nobreak >nul

echo.
echo Starting Auth Service...
docker-compose up -d auth-service

echo.
echo ========================================
echo Service-1 is starting...
echo Service will run at: http://localhost:8081
echo Swagger UI: http://localhost:8081/swagger
echo Health Check: http://localhost:8081/health
echo ========================================
echo.
echo To view logs: docker logs -f auth-service
echo To stop: docker-compose stop auth-service
echo.

pause

