#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
StartHub — Behavior AI Microservice
FastAPI + Isolation Forest (scikit-learn)
Détection d'anomalies comportementales utilisateur
"""

import logging
import os
import time
from datetime import datetime
from typing import Optional

import numpy as np
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from sklearn.ensemble import IsolationForest

# ─────────────────────────────────────────────────────────────
# LOGGING
# ─────────────────────────────────────────────────────────────
logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger("behavior_ai")

# ─────────────────────────────────────────────────────────────
# APP
# ─────────────────────────────────────────────────────────────
app = FastAPI(title="StartHub Behavior AI", version="1.0.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://127.0.0.1:8000", "http://localhost:8000"],
    allow_methods=["POST", "GET"],
    allow_headers=["*"],
)

# ─────────────────────────────────────────────────────────────
# MODÈLE Isolation Forest (entraîné sur comportement normal)
# ─────────────────────────────────────────────────────────────
# Données d'entraînement simulant un comportement normal :
# [heure_connexion, durée_session_min, connexions_7j, heure_habituelle_moy]
_NORMAL_DATA = np.array([
    [9,  30, 5, 10], [10, 45, 6, 10], [11, 60, 5, 10],
    [14, 40, 4, 14], [15, 50, 5, 14], [16, 35, 6, 14],
    [18, 55, 7, 19], [19, 70, 8, 19], [20, 60, 7, 19],
    [8,  25, 4, 9],  [12, 30, 5, 12], [17, 45, 6, 17],
    [9,  35, 5, 10], [10, 50, 6, 10], [14, 40, 5, 14],
    [18, 60, 7, 19], [19, 65, 8, 19], [20, 55, 7, 19],
    [8,  20, 4, 9],  [11, 45, 5, 11], [15, 50, 6, 15],
])

_model = IsolationForest(contamination=0.1, random_state=42, n_estimators=100)
_model.fit(_NORMAL_DATA)
logger.info("✅ Modèle Isolation Forest entraîné (%d samples)", len(_NORMAL_DATA))

# ─────────────────────────────────────────────────────────────
# SCHÉMAS
# ─────────────────────────────────────────────────────────────
class BehaviorInput(BaseModel):
    user_id: int
    login_time: str           # ISO 8601 : "2026-05-02T03:15:00"
    session_duration: int     # minutes
    logins_last_7_days: int
    usual_login_hour: Optional[float] = None  # heure moyenne habituelle
    ip_address: Optional[str] = None
    timezone: Optional[str] = None

class AnomalyDetails(BaseModel):
    unusual_login_time: bool
    unusual_session_duration: bool
    unusual_activity_frequency: bool

class BehaviorResult(BaseModel):
    anomaly: bool
    score: float              # 0.0 = normal, 1.0 = très suspect
    risk_level: str           # low | medium | high
    message: str
    details: AnomalyDetails
    processing_ms: float

# ─────────────────────────────────────────────────────────────
# HELPERS
# ─────────────────────────────────────────────────────────────
def _parse_hour(login_time: str) -> float:
    try:
        dt = datetime.fromisoformat(login_time)
        return dt.hour + dt.minute / 60.0
    except Exception:
        return 12.0


def _risk_level(score: float) -> str:
    if score < 0.3:
        return "low"
    if score < 0.6:
        return "medium"
    return "high"


def _build_message(anomaly: bool, risk: str, details: AnomalyDetails) -> str:
    if not anomaly:
        return "Comportement normal. Aucune anomalie détectée."

    reasons = []
    if details.unusual_login_time:
        reasons.append("heure de connexion inhabituelle")
    if details.unusual_session_duration:
        reasons.append("durée de session anormale")
    if details.unusual_activity_frequency:
        reasons.append("fréquence d'activité suspecte")

    base = f"⚠️ Comportement suspect détecté ({risk.upper()}) : "
    return base + ", ".join(reasons) + "." if reasons else base + "pattern inhabituel."


def _ms(t0: float) -> float:
    return round((time.perf_counter() - t0) * 1000, 2)

# ─────────────────────────────────────────────────────────────
# ENDPOINT PRINCIPAL
# ─────────────────────────────────────────────────────────────
@app.post("/analyze-behavior", response_model=BehaviorResult)
def analyze_behavior(data: BehaviorInput) -> BehaviorResult:
    t0 = time.perf_counter()

    login_hour    = _parse_hour(data.login_time)
    usual_hour    = data.usual_login_hour if data.usual_login_hour is not None else login_hour
    session_dur   = max(0, data.session_duration)
    logins_7d     = max(0, data.logins_last_7_days)

    # Vecteur de features
    features = np.array([[login_hour, session_dur, logins_7d, usual_hour]])

    # Score Isolation Forest (-1 = anomalie, 1 = normal)
    raw_score    = _model.decision_function(features)[0]   # plus négatif = plus suspect
    prediction   = _model.predict(features)[0]             # -1 ou 1

    # Normalisation du score entre 0 et 1 (0 = normal, 1 = suspect)
    anomaly_score = round(float(np.clip((-raw_score + 0.5) / 1.0, 0.0, 1.0)), 3)
    is_anomaly    = prediction == -1

    # Analyse détaillée par règles métier
    hour_diff             = abs(login_hour - usual_hour)
    unusual_time          = login_hour < 5 or login_hour > 23 or hour_diff > 6
    unusual_session       = session_dur < 1 or session_dur > 480  # < 1 min ou > 8h
    unusual_frequency     = logins_7d > 50 or (logins_7d == 0 and session_dur > 0)

    # Si les règles métier détectent une anomalie, on force is_anomaly
    if unusual_time or unusual_session or unusual_frequency:
        is_anomaly = True
        anomaly_score = max(anomaly_score, 0.4)

    details = AnomalyDetails(
        unusual_login_time=unusual_time,
        unusual_session_duration=unusual_session,
        unusual_activity_frequency=unusual_frequency,
    )

    risk    = _risk_level(anomaly_score)
    message = _build_message(is_anomaly, risk, details)

    logger.info(
        "user=%d hour=%.1f session=%dmin logins7d=%d → anomaly=%s score=%.3f risk=%s",
        data.user_id, login_hour, session_dur, logins_7d, is_anomaly, anomaly_score, risk
    )

    return BehaviorResult(
        anomaly=is_anomaly,
        score=anomaly_score,
        risk_level=risk,
        message=message,
        details=details,
        processing_ms=_ms(t0),
    )


@app.get("/health")
def health():
    return {"status": "ok", "service": "behavior-ai", "version": "1.0.0"}


if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host="0.0.0.0", port=8002, reload=False)
