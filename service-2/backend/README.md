Music Transcriber - Microservice

This folder contains a FastAPI-based microservice that accepts audio uploads and returns note events and an optional generated MIDI file.

Quick start (development)

1. Install dependencies:

   python -m pip install -r requirements.txt

2. Run locally:

   # from inside backend/
   python main.py

   or

   uvicorn main:app --reload

API endpoints
- POST /api/v1/trans  (or /trans if API_PREFIX is empty) -- upload audio file (form field `file`) and receive transcription and midi link
- GET  /api/v1/trans/midi/{midi_filename} -- download generated MIDI
- GET  /health -- health check
- GET  /ready  -- readiness check

Docker
- Build image: docker build -t transcriber:latest .
- Run: docker run -p 8000:8000 transcriber:latest
- Or use docker-compose up --build

Configuration
- Use environment variables to set HOST, PORT, RELOAD, API_PREFIX
- Example: use `.env.example` as a template

Security
- Limit CORS in production and run behind a reverse proxy (Nginx) with TLS.

