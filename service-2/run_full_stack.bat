@echo off
REM Script to run Music Transcriber microservice with frontend
REM This runs both backend (port 8000) and frontend (port 8080)

echo.
echo ================================================
echo Music Transcriber - Full Stack
echo ================================================
echo.
echo Frontend: http://localhost:8080
echo Backend:  http://localhost:8000
echo API Docs: http://localhost:8000/docs
echo.

REM Start backend in background
echo Starting backend server...
start "MuTraPro Backend" cmd /k "cd /d C:\audio && .\venv\Scripts\python.exe backend/main.py"

REM Wait a bit for backend to start
timeout /t 2 /nobreak

REM Start frontend in background
echo Starting frontend server...
start "MuTraPro Frontend" cmd /k "cd /d C:\audio\frontend && ..\..\venv\Scripts\python.exe -m http.server 8080"

REM Wait a bit and open browser
timeout /t 2 /nobreak
echo.
echo Opening browser... http://localhost:8080
start http://localhost:8080

echo.
echo ================================================
echo Services running:
echo  - Backend API: http://localhost:8000
echo  - Frontend UI: http://localhost:8080
echo  - Swagger Docs: http://localhost:8000/docs
echo.
echo Press Ctrl+C in either window to stop.
echo ================================================
