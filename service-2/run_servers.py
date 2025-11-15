#!/usr/bin/env python3
"""
Simple server to run both backend and frontend
Run: python run_servers.py
or: .\venv\Scripts\python.exe run_servers.py
"""
import subprocess
import time
import webbrowser
import os
import sys

def main():
    print("=" * 60)
    print("Music Transcriber - Full Stack Server")
    print("=" * 60)
    print()
    print("Frontend: http://localhost:8080")
    print("Backend:  http://localhost:8000")
    print("API Docs: http://localhost:8000/docs")
    print()
    
    # Get paths
    audio_dir = os.path.dirname(os.path.abspath(__file__))
    backend_dir = os.path.join(audio_dir, "backend")
    frontend_dir = os.path.join(audio_dir, "frontend")
    
    # Try to find Python executable in venv
    venv_python = None
    possible_paths = [
        os.path.join(audio_dir, "venv", "Scripts", "python.exe"),
        os.path.join(audio_dir, "venv", "Scripts", "python"),
        "python.exe",
        "python"
    ]
    
    for path in possible_paths:
        if os.path.exists(path):
            venv_python = path
            break
    
    if not venv_python:
        print("ERROR: Could not find Python executable!")
        print("Tried:")
        for path in possible_paths:
            print(f"  - {path}")
        sys.exit(1)
    
    print("Starting services...")
    print(f"Using Python: {venv_python}")
    print()
    
    # Start backend
    try:
        print("[1/2] Starting backend (port 8000)...")
        backend_proc = subprocess.Popen(
            [venv_python, "main.py"],
            cwd=backend_dir
        )
        print(f"      Backend started (PID: {backend_proc.pid})")
    except Exception as e:
        print(f"ERROR: Failed to start backend: {e}")
        sys.exit(1)
    
    # Wait for backend to be ready
    time.sleep(2)
    
    # Start frontend
    try:
        print("[2/2] Starting frontend (port 8080)...")
        frontend_proc = subprocess.Popen(
            [venv_python, "-m", "http.server", "8080"],
            cwd=frontend_dir
        )
        print(f"      Frontend started (PID: {frontend_proc.pid})")
    except Exception as e:
        print(f"ERROR: Failed to start frontend: {e}")
        backend_proc.terminate()
        sys.exit(1)
    
    # Wait a bit and open browser
    time.sleep(2)
    print()
    print("=" * 60)
    print("Services running!")
    print("=" * 60)
    print()
    print("Opening browser...")
    
    try:
        webbrowser.open("http://localhost:8080")
    except Exception as e:
        print(f"Could not open browser: {e}")
        print("Please open http://localhost:8080 manually")
    
    print()
    print("Press Ctrl+C to stop all services...")
    print()
    
    try:
        # Keep running
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        print()
        print("Shutting down...")
        backend_proc.terminate()
        frontend_proc.terminate()
        try:
            backend_proc.wait(timeout=3)
            frontend_proc.wait(timeout=3)
        except subprocess.TimeoutExpired:
            backend_proc.kill()
            frontend_proc.kill()
        print("Done!")

if __name__ == "__main__":
    main()


if __name__ == "__main__":
    main()
