#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
StartHub — Photo Validation Microservice
FastAPI + OpenCV (Haar Cascades) + Tesseract OCR
"""

import logging
import os
import time

import cv2
import numpy as np
import pytesseract
from fastapi import FastAPI, File, HTTPException, Security, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from fastapi.security.api_key import APIKeyHeader
from pydantic import BaseModel

# ─────────────────────────────────────────────────────────────
# CONFIG
# ─────────────────────────────────────────────────────────────
logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger("photo_validator")

API_KEY       = os.environ.get("PHOTO_API_KEY", "starthub-photo-secret")
MAX_FILE_SIZE = 5 * 1024 * 1024
ALLOWED_TYPES = {"image/jpeg", "image/png", "image/webp"}

pytesseract.pytesseract.tesseract_cmd = r"C:\Program Files\Tesseract-OCR\tesseract.exe"

# ─────────────────────────────────────────────────────────────
# APP
# ─────────────────────────────────────────────────────────────
app = FastAPI(title="StartHub Photo Validator", version="2.0.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://127.0.0.1:8000", "http://localhost:8000"],
    allow_methods=["POST", "GET"],
    allow_headers=["*"],
)

api_key_header = APIKeyHeader(name="X-API-Key", auto_error=False)

def require_api_key(key: str = Security(api_key_header)) -> str:
    if not API_KEY or key == API_KEY:
        return key
    raise HTTPException(status_code=403, detail="Clé API invalide")

# ─────────────────────────────────────────────────────────────
# MODÈLES
# ─────────────────────────────────────────────────────────────
logger.info("Chargement des détecteurs OpenCV...")
_face_cascade    = cv2.CascadeClassifier(cv2.data.haarcascades + "haarcascade_frontalface_default.xml")
_profile_cascade = cv2.CascadeClassifier(cv2.data.haarcascades + "haarcascade_profileface.xml")
logger.info("Détecteurs prêts.")

# ─────────────────────────────────────────────────────────────
# SCHÉMA DE RÉPONSE
# ─────────────────────────────────────────────────────────────
class Checks(BaseModel):
    face_detected: bool
    nsfw: bool
    meme: bool
    quality_ok: bool

class ValidationResult(BaseModel):
    success: bool
    valid: bool
    code: str          # VALID | NO_FACE | NSFW | MEME_DETECTED | ERROR
    message: str
    faces_detected: int
    confidence: float
    checks: Checks
    reason: str
    processing_ms: float

# ─────────────────────────────────────────────────────────────
# HELPERS
# ─────────────────────────────────────────────────────────────
def _decode(data: bytes) -> np.ndarray:
    arr = np.frombuffer(data, np.uint8)
    img = cv2.imdecode(arr, cv2.IMREAD_COLOR)
    if img is None:
        raise ValueError("Image illisible")
    return img


def _detect_faces(img: np.ndarray) -> tuple[int, float]:
    """Retourne (nb_visages, confiance 0-1)."""
    h, w = img.shape[:2]
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    gray = cv2.equalizeHist(gray)
    min_face = max(40, int(min(h, w) * 0.10))

    faces = _face_cascade.detectMultiScale(
        gray, scaleFactor=1.1, minNeighbors=5, minSize=(min_face, min_face)
    )
    if len(faces) == 0:
        faces = _profile_cascade.detectMultiScale(
            gray, scaleFactor=1.1, minNeighbors=5, minSize=(min_face, min_face)
        )

    if len(faces) == 0:
        return 0, 0.0

    img_area = h * w
    valid = []
    for (x, y, fw, fh) in faces:
        if (fw * fh) / img_area < 0.02:
            continue
        roi = img[y:y+fh, x:x+fw]
        if _has_skin(roi):
            valid.append((fw * fh) / img_area)

    if not valid:
        return 0, 0.0

    confidence = min(1.0, round(sum(valid) / len(valid) * 5, 2))
    return len(valid), confidence


def _has_skin(roi: np.ndarray) -> bool:
    hsv = cv2.cvtColor(roi, cv2.COLOR_BGR2HSV)
    mask = cv2.inRange(hsv, np.array([0, 15, 50]), np.array([30, 255, 255]))
    return (np.count_nonzero(mask) / (roi.shape[0] * roi.shape[1])) > 0.08


def _detect_meme(img: np.ndarray) -> bool:
    """Détecte mème via OCR (texte > 8 chars) ou image trop uniforme."""
    # Image uniforme = logo/icône
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    if float(np.std(gray)) < 15.0:
        return True
    # OCR
    try:
        text = pytesseract.image_to_string(gray, config="--psm 11").strip()
        if len(text) >= 8:
            logger.info("Texte OCR détecté (%d chars): %s", len(text), text[:60])
            return True
    except Exception:
        pass
    return False


def _ms(t0: float) -> float:
    return round((time.perf_counter() - t0) * 1000, 2)

# ─────────────────────────────────────────────────────────────
# ENDPOINT
# ─────────────────────────────────────────────────────────────
@app.post("/validate-photo", response_model=ValidationResult)
async def validate_photo(
    file: UploadFile = File(...),
    _key: str = Security(require_api_key),
) -> ValidationResult:
    t0 = time.perf_counter()

    # 1. Type MIME
    if file.content_type not in ALLOWED_TYPES:
        return ValidationResult(
            success=True, valid=False, code="ERROR",
            message="Format non supporté. Utilisez JPEG, PNG ou WebP.",
            faces_detected=0, confidence=0.0,
            checks=Checks(face_detected=False, nsfw=False, meme=False, quality_ok=False),
            reason=f"MIME type invalide: {file.content_type}",
            processing_ms=_ms(t0),
        )

    # 2. Taille
    data = await file.read()
    if len(data) > MAX_FILE_SIZE:
        return ValidationResult(
            success=True, valid=False, code="ERROR",
            message="L'image dépasse 5 Mo.",
            faces_detected=0, confidence=0.0,
            checks=Checks(face_detected=False, nsfw=False, meme=False, quality_ok=False),
            reason="Fichier trop volumineux",
            processing_ms=_ms(t0),
        )

    # 3. Décodage
    try:
        img = _decode(data)
    except ValueError:
        return ValidationResult(
            success=True, valid=False, code="ERROR",
            message="Fichier image corrompu ou illisible.",
            faces_detected=0, confidence=0.0,
            checks=Checks(face_detected=False, nsfw=False, meme=False, quality_ok=False),
            reason="Décodage impossible",
            processing_ms=_ms(t0),
        )

    # 4. Détection visage D'ABORD
    face_count, confidence = _detect_faces(img)

    # 5. Si visage trouvé → valide directement
    if face_count > 3:
        return ValidationResult(
            success=True, valid=False, code="NO_FACE",
            message="❌ Plusieurs visages détectés. La photo doit vous représenter seul(e).",
            faces_detected=face_count, confidence=confidence,
            checks=Checks(face_detected=True, nsfw=False, meme=False, quality_ok=False),
            reason="Trop de visages détectés",
            processing_ms=_ms(t0),
        )

    if face_count > 0:
        logger.info("✅ Photo validée — %d visage(s), confiance=%.2f, %.0fms", face_count, confidence, _ms(t0))
        return ValidationResult(
            success=True, valid=True, code="VALID",
            message="✅ Photo de profil valide.",
            faces_detected=face_count, confidence=confidence,
            checks=Checks(face_detected=True, nsfw=False, meme=False, quality_ok=True),
            reason="Visage humain détecté avec couleur peau valide",
            processing_ms=_ms(t0),
        )

    # 6. Pas de visage → vérifier si c'est un mème
    is_meme = _detect_meme(img)
    if is_meme:
        return ValidationResult(
            success=True, valid=False, code="MEME_DETECTED",
            message="❌ Cette image ressemble à un mème ou contient du texte. Veuillez utiliser une vraie photo de profil.",
            faces_detected=0, confidence=0.0,
            checks=Checks(face_detected=False, nsfw=False, meme=True, quality_ok=False),
            reason="Texte détecté par OCR ou image uniforme",
            processing_ms=_ms(t0),
        )

    # 7. Pas de visage, pas de mème → image non pertinente
    return ValidationResult(
        success=True, valid=False, code="NO_FACE",
        message="❌ Aucun visage humain détecté. Veuillez utiliser une photo de profil claire avec votre visage.",
        faces_detected=0, confidence=0.0,
        checks=Checks(face_detected=False, nsfw=False, meme=False, quality_ok=False),
        reason="Haar Cascade + skin color: aucun visage valide",
        processing_ms=_ms(t0),
    )


@app.get("/health")
def health():
    return {"status": "ok", "service": "photo-validator", "version": "2.0.0"}


if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host="0.0.0.0", port=8001, reload=False)
