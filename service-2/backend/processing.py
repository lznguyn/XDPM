
import io
import numpy as np
import librosa
import soundfile as sf
import pretty_midi
from typing import List, Tuple

_NOTE_NAMES = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B']

def midi_to_note_name(midi: int) -> str:
    octave = midi // 12 - 1
    name = _NOTE_NAMES[midi % 12]
    return f"{name}{octave}"

def freq_to_midi_note(f):
    return int(np.round(librosa.hz_to_midi(f)))

def extract_notes_from_audio_bytes(wav_bytes: bytes, sr_target=22050,
                                     fmin=65.41, fmax=1975.53,
                                     hop_length=512, frame_length=2048) -> Tuple[List[Tuple[str,float,float]], pretty_midi.PrettyMIDI]:
    """Return events list and PrettyMIDI object.
    events: list of (note_name, t_start, t_end)
    """
    bio = io.BytesIO(wav_bytes)
    y, sr = sf.read(bio, dtype='float32')
    # to mono
    if y.ndim > 1:
        y = np.mean(y, axis=1)
    if sr != sr_target:
        y = librosa.resample(y, orig_sr=sr, target_sr=sr_target)
        sr = sr_target

    # pitch tracking using pyin (monophonic melody estimation)
    try:
        f0, voiced_flag, voiced_probs = librosa.pyin(
            y,
            fmin=fmin,
            fmax=fmax,
            sr=sr,
            hop_length=hop_length,
            frame_length=frame_length,
        )
        times = librosa.frames_to_time(np.arange(len(f0)), sr=sr, hop_length=hop_length)
    except Exception:
        # if pyin fails (very short audio or unsupported), return empty results
        return [], None

    midi_notes = np.full_like(f0, fill_value=np.nan)
    for i, (f, v) in enumerate(zip(f0, voiced_flag)):
        if v and not np.isnan(f):
            midi_notes[i] = freq_to_midi_note(f)

    events = []
    i = 0
    n = len(midi_notes)
    while i < n:
        if np.isnan(midi_notes[i]):
            i += 1
            continue
        note_val = int(midi_notes[i])
        t_start = float(times[i])
        j = i + 1
        while j < n and (not np.isnan(midi_notes[j])) and int(np.round(midi_notes[j])) == note_val:
            j += 1
        t_end = float(times[j-1] + (hop_length / sr))
        events.append((midi_to_note_name(note_val), t_start, t_end))
        i = j

    # MIDI object
    pm = pretty_midi.PrettyMIDI()
    piano = pretty_midi.Instrument(program=0)
    for note_name, t0, t1 in events:
        try:
            midi_num = pretty_midi.note_name_to_number(note_name)
        except Exception:
            continue
        note_obj = pretty_midi.Note(velocity=100, pitch=midi_num, start=t0, end=t1)
        piano.notes.append(note_obj)
    pm.instruments.append(piano)

    return events, pm
