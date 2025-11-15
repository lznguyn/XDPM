import os
import time
import uuid
import logging
from fastapi import FastAPI, File, UploadFile, HTTPException
from fastapi.responses import FileResponse
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles
import uvicorn
from processing import extract_notes_from_audio_bytes  

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
UPLOAD_DIR = os.path.join(BASE_DIR, "uploads")
OUTPUT_DIR = os.path.join(BASE_DIR, "outputs")
os.makedirs(UPLOAD_DIR, exist_ok=True)
os.makedirs(OUTPUT_DIR, exist_ok=True)

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("mutrapro")

app = FastAPI(title="Music Transcriber - Offline")

# CORS configuration: allow configuring allowed origins via environment variable
# Set ALLOWED_ORIGINS to a comma-separated list of origins (e.g. https://example.com,http://localhost:3000)
allowed_origins_env = os.environ.get("ALLOWED_ORIGINS", "*")
if allowed_origins_env.strip() == "*":
    allowed_origins = ["*"]
else:
    allowed_origins = [o.strip() for o in allowed_origins_env.split(',') if o.strip()]

app.add_middleware(
    CORSMiddleware,
    allow_origins=allowed_origins,
    allow_methods=["*"],
    allow_headers=["*"],
)

# --- configuration for microservice use -------------------------------------------------
# Read runtime configuration from environment so this can be used as a standalone service
API_PREFIX = os.environ.get("API_PREFIX", "/api/v1")
# normalize: allow empty string to mean no prefix
if API_PREFIX == "" or API_PREFIX is None:
    API_PREFIX = ""
# ensure prefix starts with slash when non-empty
elif not API_PREFIX.startswith("/"):
    API_PREFIX = f"/{API_PREFIX}"

from fastapi import APIRouter
router = APIRouter()

# serve outputs for MIDI download (static) at both root and prefixed path for flexibility
app.mount("/outputs", StaticFiles(directory=OUTPUT_DIR), name="outputs")
if API_PREFIX:
    app.mount(f"{API_PREFIX}/outputs", StaticFiles(directory=OUTPUT_DIR), name="outputs_prefixed")
# NOTE: Do NOT mount "/" with static files as it will shadow all API routes
# Instead, serve frontend separately or use a reverse proxy (nginx) in front
# ----------------------------------------------------------------------------------------

@router.post("/trans")
async def trans(file: UploadFile = File(...)):
    # basic validation
    if not file.filename.lower().endswith((".wav", ".flac", ".mp3", ".ogg", ".aiff")):
        raise HTTPException(status_code=400, detail="Only audio files are supported (.wav .mp3 .flac .ogg .aiff)")

    contents = await file.read()
    # save upload for reference
    fname = f"{uuid.uuid4().hex}_{file.filename}"
    fpath = os.path.join(UPLOAD_DIR, fname)
    with open(fpath, "wb") as f:
        f.write(contents)
    logger.info("Saved uploaded file to %s", fpath)

    try:
        # extract_notes_from_audio_bytes should return (events, pretty_midi_object)
        events_raw, pm = extract_notes_from_audio_bytes(contents)
    except Exception as e:
        logger.exception("Error extracting notes")
        raise HTTPException(status_code=500, detail=f"Processing error: {e}")

    # normalize events to list of dicts {note, start, end}
    events = []
    for item in events_raw:
        # accept tuple/list (note, t0, t1) or dict
        if isinstance(item, (list, tuple)) and len(item) >= 3:
            note, t0, t1 = item[0], float(item[1]), float(item[2])
        elif isinstance(item, dict):
            note = item.get("note") or item.get("name") or item.get(0)
            t0 = float(item.get("start", item.get("t0", 0)))
            t1 = float(item.get("end", item.get("t1", t0 + 0.0)))
        else:
            continue
        events.append({"note": str(note), "start": float(t0), "end": float(t1)})

    # build transcription_text (durations)
    text_parts = []
    for e in events:
        dur = e["end"] - e["start"]
        text_parts.append(f"{e['note']}({dur:.3f}s)")
    text_output = " ".join(text_parts)

    # write midi if pretty_midi object present
    midi_name = f"{os.path.splitext(fname)[0]}.mid"
    midi_path = os.path.join(OUTPUT_DIR, midi_name)
    midi_rel_url = None
    if pm is not None:
        try:
            pm.write(midi_path)
            # ensure file exists on disk
            timeout = 3.0
            waited = 0.0
            while not os.path.isfile(midi_path) and waited < timeout:
                time.sleep(0.05)
                waited += 0.05
            if os.path.isfile(midi_path):
                midi_rel_url = f"/outputs/{midi_name}"
                logger.info("Wrote MIDI to %s", midi_path)
            else:
                logger.warning("MIDI write attempted but file not found after waiting")
        except Exception as e:
            logger.exception("Failed to write MIDI file: %s", e)
            midi_rel_url = None

    response = {
        "success": True,
        "transcription_text": text_output,
        "events": events,   # normalized events as list of dicts
        "midi_file": midi_rel_url
    }
    return response

@router.get("/trans/midi/{midi_filename}")
def get_midi(midi_filename: str):
    path = os.path.join(OUTPUT_DIR, midi_filename)
    if not os.path.isfile(path):
        raise HTTPException(status_code=404, detail="MIDI not found")
    return FileResponse(path, media_type="audio/midi", filename=midi_filename)


# health and readiness endpoints (root-level)
@app.get("/health")
def health_check():
    return {"status": "ok"}


@app.get("/ready")
def readiness_check():
    # could include checks for disk, model load, etc. For now return healthy.
    return {"status": "ready"}


# include router under configured prefix (empty string or "/api/v1" etc.)
app.include_router(router, prefix=API_PREFIX)

if __name__ == "__main__":
    # configure runtime from environment variables so the same image can be used
    host = os.environ.get("HOST", "0.0.0.0")
    port = int(os.environ.get("PORT", "8000"))
    # set RELOAD=true to enable auto-reload in development
    reload_flag = os.environ.get("RELOAD", "false").lower() in ("1", "true", "yes")
    uvicorn.run("main:app", host=host, port=port, reload=reload_flag)
