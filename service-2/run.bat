
@echo off
REM Activate venv and start server
cd backend
if exist "..\venv\Scripts\activate.bat" (
  call ..\venv\Scripts\activate.bat
)
REM Run uvicorn
uvicorn main:app --reload --port 8000
pause
