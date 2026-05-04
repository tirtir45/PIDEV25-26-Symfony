#!/usr/bin/env python3
# -*- coding: utf-8 -*-
from __future__ import annotations

import base64
import csv
import hashlib
import io
import json
import logging
import os
import random
import re
import sys
import time as _time
import unicodedata
from collections import Counter
from concurrent.futures import ThreadPoolExecutor, as_completed
from dataclasses import dataclass, field, asdict
from datetime import datetime
from functools import lru_cache, wraps
from pathlib import Path
from threading import Lock
from typing import Any, Generator

# ============================================================
# CHARGEMENT DES VARIABLES D'ENVIRONNEMENT (WINDOWS FIX)
# ============================================================
# Windows specific fixes
if sys.platform == "win32":
    import locale
    try:
        locale.setlocale(locale.LC_ALL, 'fr_FR.UTF-8')
    except:
        pass
    os.environ.setdefault('PYTHONUTF8', '1')

# Charger .env manuellement
env_file = Path(__file__).parent / '.env'
if env_file.exists():
    print(f"📁 Chargement du fichier .env depuis {env_file}")
    with open(env_file, 'r', encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            if line and not line.startswith('#'):
                key, value = line.split('=', 1)
                os.environ[key] = value
                if key == 'GROQ_API_KEY':
                    print(f"🔑 GROQ_API_KEY chargée (longueur: {len(value)} caractères)")
                else:
                    print(f"   {key} = {value}")
else:
    print(f"⚠️  Fichier .env non trouvé dans {env_file}")
    # Définir la clé par défaut si .env n'existe pas
    if not os.environ.get("GROQ_API_KEY"):
        os.environ["GROQ_API_KEY"] = "" # REPLACE_ME: Configure in .env
        print("🔑 GROQ_API_KEY définie par défaut")

# Vérification finale
print(f"\n✅ Variables d'environnement chargées:")
print(f"   GROQ_API_KEY présente: {bool(os.environ.get('GROQ_API_KEY'))}")
print(f"   PORT: {os.environ.get('PORT', '5000')}")
print("=" * 50)

# Ensuite le reste des imports et le code...
import nltk
from cachetools import TTLCache, cached
from flask import Flask, Response, jsonify, request, stream_with_context
from flask_compress import Compress
from flask_cors import CORS
from flask_limiter import Limiter
from flask_limiter.util import get_remote_address
from markupsafe import escape
from textblob import TextBlob

# ... LE RESTE DE VOTRE CODE ICI ...

# ─────────────────────────────────────────────────────────────────────────────
# LOGGING
# ─────────────────────────────────────────────────────────────────────────────
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s — %(message)s",
)
logger = logging.getLogger("starthub")

# ─────────────────────────────────────────────────────────────────────────────
# NLTK BOOTSTRAP
# ─────────────────────────────────────────────────────────────────────────────
for _resource in ("tokenizers/punkt", "corpora/stopwords"):
    try:
        nltk.data.find(_resource)
    except LookupError:
        nltk.download(_resource.split("/")[1], quiet=True)

# ─────────────────────────────────────────────────────────────────────────────
# OPTIONAL LLM BACKEND (OpenAI-compatible)
# ─────────────────────────────────────────────────────────────────────────────
try:
    from openai import OpenAI as _OpenAI
    _LLM_AVAILABLE = bool(os.environ.get("OPENAI_API_KEY") or os.environ.get("GROQ_API_KEY"))
    if os.environ.get("GROQ_API_KEY"):
        _llm_client = _OpenAI(
            api_key=os.environ["GROQ_API_KEY"],
            base_url="https://api.groq.com/openai/v1",
        )
        _LLM_MODEL = "llama-3.3-70b-versatile"
        print(f"✅ LLM configuré avec Groq (modèle: {_LLM_MODEL})")
    elif os.environ.get("OPENAI_API_KEY"):
        _llm_client = _OpenAI(api_key=os.environ["OPENAI_API_KEY"])
        _LLM_MODEL = "gpt-4o-mini"
        print(f"✅ LLM configuré avec OpenAI (modèle: {_LLM_MODEL})")
    else:
        _llm_client = None
        _LLM_MODEL = None
        print("⚠️ Aucune clé API LLM trouvée (mode heuristique uniquement)")
except ImportError:
    _LLM_AVAILABLE = False
    _llm_client = None
    _LLM_MODEL = None
    print("⚠️ OpenAI library non installée (pip install openai)")

# ─────────────────────────────────────────────────────────────────────────────
# OPTIONAL PROMETHEUS METRICS
# ─────────────────────────────────────────────────────────────────────────────
try:
    from prometheus_client import Counter as PCounter, Histogram, generate_latest, CONTENT_TYPE_LATEST
    _req_counter = PCounter("starthub_requests_total", "Total API requests", ["endpoint", "status"])
    _req_latency = Histogram("starthub_request_duration_seconds", "Request latency", ["endpoint"])
    _PROMETHEUS = True
    print("✅ Prometheus metrics activé")
except ImportError:
    _PROMETHEUS = False
    print("⚠️ Prometheus client non installé (pip install prometheus-client)")

# ─────────────────────────────────────────────────────────────────────────────
# OPTIONAL JSONSCHEMA
# ─────────────────────────────────────────────────────────────────────────────
try:
    from jsonschema import validate as _jschema_validate, ValidationError as _JValidationError
    _JSONSCHEMA = True
    print("✅ JSON Schema validation activé")
except ImportError:
    _JSONSCHEMA = False
    print("⚠️ jsonschema non installé (pip install jsonschema)")

# ─────────────────────────────────────────────────────────────────────────────
# APP SETUP
# ─────────────────────────────────────────────────────────────────────────────
app = Flask(__name__)
app.config["MAX_CONTENT_LENGTH"] = 1 * 1024 * 1024  # 1 MB

API_KEY: str = os.environ.get("STARTHUB_API_KEY", "")
DEBUG: bool = os.environ.get("FLASK_DEBUG", "0") == "1"

CORS(app, origins=os.environ.get("CORS_ORIGINS", "*").split(","))
Compress(app)

limiter = Limiter(
    app=app,
    key_func=get_remote_address,
    default_limits=["1000 per day", "200 per hour"],
    storage_uri="memory://",
)

# Thread pool pour tâches parallèles (batch endpoint)
_executor = ThreadPoolExecutor(max_workers=int(os.environ.get("WORKER_THREADS", "4")))

# ─────────────────────────────────────────────────────────────────────────────
# CIRCUIT BREAKER (pour l'API LLM externe)
# ─────────────────────────────────────────────────────────────────────────────
class CircuitBreaker:
    """Simple circuit breaker pour protéger les appels LLM externes."""
    CLOSED, OPEN, HALF_OPEN = "closed", "open", "half_open"

    def __init__(self, failure_threshold=5, recovery_timeout=60):
        self.state = self.CLOSED
        self.failures = 0
        self.failure_threshold = failure_threshold
        self.recovery_timeout = recovery_timeout
        self.last_failure_time: float | None = None
        self._lock = Lock()

    def call(self, func, *args, **kwargs):
        with self._lock:
            if self.state == self.OPEN:
                if _time.time() - self.last_failure_time > self.recovery_timeout:
                    self.state = self.HALF_OPEN
                    logger.info("[CircuitBreaker] → HALF_OPEN")
                else:
                    raise RuntimeError("Circuit breaker OPEN — LLM indisponible temporairement")
        try:
            result = func(*args, **kwargs)
            with self._lock:
                self.failures = 0
                self.state = self.CLOSED
            return result
        except Exception as e:
            with self._lock:
                self.failures += 1
                self.last_failure_time = _time.time()
                if self.failures >= self.failure_threshold:
                    self.state = self.OPEN
                    logger.error("[CircuitBreaker] → OPEN après %d échecs", self.failures)
            raise

_llm_breaker = CircuitBreaker(failure_threshold=5, recovery_timeout=60)

# ─────────────────────────────────────────────────────────────────────────────
# CACHE EN MÉMOIRE (avec TTL)
# ─────────────────────────────────────────────────────────────────────────────
_analysis_cache: TTLCache = TTLCache(
    maxsize=int(os.environ.get("CACHE_SIZE", "1000")),
    ttl=int(os.environ.get("CACHE_TTL_SECONDS", "3600")),
)
_cache_lock = Lock()
_sentiment_cache: TTLCache = TTLCache(maxsize=512, ttl=3600)
_sentiment_lock = Lock()

# Registre des webhooks
_webhooks: dict[str, list[str]] = {}  # event -> [url1, url2, ...]
_webhooks_lock = Lock()

# ─────────────────────────────────────────────────────────────────────────────
# MIDDLEWARES
# ─────────────────────────────────────────────────────────────────────────────
@app.before_request
def _start_timer():
    request._start_ts = _time.perf_counter()


@app.after_request
def _add_headers(response):
    if hasattr(request, "_start_ts"):
        elapsed_ms = round((_time.perf_counter() - request._start_ts) * 1000, 2)
        response.headers["X-Response-Time"] = f"{elapsed_ms}ms"
        if _PROMETHEUS and hasattr(request, "_endpoint"):
            _req_latency.labels(endpoint=request._endpoint).observe(elapsed_ms / 1000)
    response.headers.setdefault("X-Content-Type-Options", "nosniff")
    response.headers.setdefault("X-Frame-Options", "DENY")
    response.headers.setdefault("X-XSS-Protection", "1; mode=block")
    response.headers.setdefault("Referrer-Policy", "strict-origin-when-cross-origin")
    response.headers.setdefault("Cache-Control", "no-store")
    return response

# ─────────────────────────────────────────────────────────────────────────────
# CONSTANTES
# ─────────────────────────────────────────────────────────────────────────────
FEATURE_ICONS = ["⚡", "🚀", "💡", "⭐", "📊", "🎯"]

SECTEURS_KEYWORDS: dict[str, list[str]] = {
    "tech": ["app","logiciel","web","mobile","ia","intelligence","donnees","cloud","api",
             "digital","blockchain","iot","robotique","automatisation","saas","plateforme",
             "algorithme","machine learning","cyber","devops","microservices"],
    "sante": ["medecin","sante","patient","hopital","medical","clinique","bien-etre",
              "diagnostic","traitement","pharmaceutique","soin","teleconsultation","biotech"],
    "finance": ["finance","banque","paiement","crypto","invest","economie","budget",
                "assurance","trading","epargne","credit","fintech","defi","neobanque"],
    "education": ["education","formation","apprentissage","ecole","cours","e-learning",
                  "pedagogie","certification","competence","mooc","edtech"],
    "environnement": ["ecologie","vert","durable","energie","recyclage","carbone",
                      "renouvelable","biodiversite","climat","cleantech","greentech"],
    "commerce": ["vente","boutique","e-commerce","marche","produit","client","retail",
                 "distribution","logistique","stock","marketplace","dropshipping"],
    "social": ["communaute","reseau","social","partage","collaboration","entraide",
               "benevolat","association","impact","ngo"],
    "culturel": ["art","culture","musique","cinema","spectacle","exposition","festival",
                 "creation","patrimoine","media","streaming"],
    "agriculture": ["agriculture","ferme","recolte","semences","irrigation","agritech",
                    "elevage","bio","permaculture"],
    "transport": ["transport","mobilite","logistique","livraison","vehicule","route",
                  "ferroviaire","maritime","aerien","uberisation"],
}

COMPLEXITE_PAR_SECTEUR: dict[str, dict[str, float]] = {
    "tech":          {"technique":0.7,"reglementaire":0.4,"commerciale":0.5,"equipe":0.6,"timing":0.5},
    "sante":         {"technique":0.9,"reglementaire":0.9,"commerciale":0.5,"equipe":0.8,"timing":0.7},
    "finance":       {"technique":0.7,"reglementaire":0.9,"commerciale":0.7,"equipe":0.7,"timing":0.6},
    "education":     {"technique":0.4,"reglementaire":0.4,"commerciale":0.5,"equipe":0.4,"timing":0.4},
    "environnement": {"technique":0.7,"reglementaire":0.7,"commerciale":0.5,"equipe":0.5,"timing":0.6},
    "commerce":      {"technique":0.4,"reglementaire":0.4,"commerciale":0.8,"equipe":0.5,"timing":0.5},
    "social":        {"technique":0.3,"reglementaire":0.2,"commerciale":0.3,"equipe":0.4,"timing":0.3},
    "culturel":      {"technique":0.3,"reglementaire":0.2,"commerciale":0.5,"equipe":0.4,"timing":0.4},
    "agriculture":   {"technique":0.5,"reglementaire":0.6,"commerciale":0.5,"equipe":0.5,"timing":0.6},
    "transport":     {"technique":0.6,"reglementaire":0.7,"commerciale":0.6,"equipe":0.5,"timing":0.5},
    "general":       {"technique":0.5,"reglementaire":0.5,"commerciale":0.5,"equipe":0.5,"timing":0.5},
}

AMELIORATIONS_PAR_SECTEUR: dict[str, list[dict]] = {
    "tech": [
        {"suggestion":"Intégrer une API REST documentée (Swagger/OpenAPI).","impact":"Facilite adoption et partenariats","effort":"Moyen","priorite":"haute"},
        {"suggestion":"Adopter une architecture micro-services pour la scalabilité.","impact":"Scalabilité et maintenance améliorées","effort":"Élevé","priorite":"moyenne"},
        {"suggestion":"Ajouter une couche d'authentification OAuth2 / JWT.","impact":"Sécurité renforcée","effort":"Moyen","priorite":"haute"},
        {"suggestion":"Implémenter des tests unitaires dès la phase MVP.","impact":"Qualité et fiabilité du code","effort":"Moyen","priorite":"haute"},
        {"suggestion":"Mettre en place un pipeline CI/CD (GitHub Actions, Docker).","impact":"Déploiements plus rapides et fiables","effort":"Moyen","priorite":"moyenne"},
        {"suggestion":"Intégrer un observability stack (logs, traces, métriques).","impact":"Détection rapide des incidents","effort":"Moyen","priorite":"haute"},
    ],
    "sante": [
        {"suggestion":"Garantir la conformité RGPD/HDS pour les données de santé.","impact":"Conformité réglementaire","effort":"Élevé","priorite":"haute"},
        {"suggestion":"Intégrer un système de consentement patient éclairé.","impact":"Confiance utilisateur","effort":"Moyen","priorite":"haute"},
        {"suggestion":"Prévoir une interopérabilité HL7/FHIR avec les SI hospitaliers.","impact":"Intégration écosystème santé","effort":"Élevé","priorite":"moyenne"},
        {"suggestion":"Obtenir la certification CE Dispositif Médical si applicable.","impact":"Accès marché européen","effort":"Très élevé","priorite":"haute"},
    ],
    "finance": [
        {"suggestion":"Implémenter le chiffrement de bout en bout des transactions.","impact":"Sécurité des données financières","effort":"Élevé","priorite":"haute"},
        {"suggestion":"Prévoir la conformité PSD2/DSP2 et KYC.","impact":"Conformité réglementaire","effort":"Élevé","priorite":"haute"},
        {"suggestion":"Ajouter un tableau de bord analytique en temps réel.","impact":"Suivi et prise de décision","effort":"Moyen","priorite":"moyenne"},
    ],
    "education": [
        {"suggestion":"Intégrer un système de suivi de progression avec badges/certifications.","impact":"Engagement apprenant","effort":"Moyen","priorite":"haute"},
        {"suggestion":"Proposer du contenu adaptatif selon le niveau de l'apprenant.","impact":"Personnalisation de l'expérience","effort":"Élevé","priorite":"moyenne"},
        {"suggestion":"Ajouter le support hors-ligne (PWA) pour les zones à faible connectivité.","impact":"Accessibilité élargie","effort":"Moyen","priorite":"haute"},
    ],
    "environnement": [
        {"suggestion":"Intégrer un calculateur d'empreinte carbone certifié.","impact":"Mesure d'impact environnemental","effort":"Moyen","priorite":"haute"},
        {"suggestion":"Prévoir des certifications/labels écologiques reconnus.","impact":"Crédibilité et confiance","effort":"Moyen","priorite":"moyenne"},
    ],
    "commerce": [
        {"suggestion":"Intégrer un moteur de recommandation produit basé sur l'IA.","impact":"Augmentation du panier moyen","effort":"Élevé","priorite":"haute"},
        {"suggestion":"Proposer une expérience omnicanale (web, mobile, en magasin).","impact":"Expérience client fluide","effort":"Élevé","priorite":"moyenne"},
        {"suggestion":"Mettre en place un programme de fidélité gamifié.","impact":"Rétention et lifetime value","effort":"Moyen","priorite":"haute"},
    ],
    "social": [
        {"suggestion":"Construire un système de gamification pour fidéliser la communauté.","impact":"Engagement et rétention","effort":"Moyen","priorite":"haute"},
        {"suggestion":"Intégrer des outils de modération automatisée (IA).","impact":"Sécurité communauté","effort":"Moyen","priorite":"haute"},
    ],
    "culturel": [
        {"suggestion":"Développer une billetterie numérique intégrée.","impact":"Simplification de l'accès","effort":"Moyen","priorite":"haute"},
        {"suggestion":"Créer un espace de streaming ou diffusion en ligne.","impact":"Audience élargie","effort":"Élevé","priorite":"moyenne"},
    ],
    "agriculture": [
        {"suggestion":"Intégrer des capteurs IoT pour le monitoring en temps réel.","impact":"Optimisation des rendements","effort":"Élevé","priorite":"haute"},
        {"suggestion":"Utiliser l'imagerie satellite pour le suivi des cultures.","impact":"Détection précoce des problèmes","effort":"Moyen","priorite":"moyenne"},
    ],
    "transport": [
        {"suggestion":"Intégrer une solution de routage optimisé en temps réel.","impact":"Réduction des coûts de carburant","effort":"Moyen","priorite":"haute"},
        {"suggestion":"Proposer des intégrations MaaS (Mobility as a Service).","impact":"Expérience multimodale","effort":"Élevé","priorite":"moyenne"},
    ],
}

AMELIORATIONS_GENERIQUES: list[dict] = [
    {"suggestion":"Définir des KPIs clairs et mesurables dès le lancement.","impact":"Suivi objectif de la progression","effort":"Faible","priorite":"haute"},
    {"suggestion":"Mettre en place un processus de feedback utilisateur continu.","impact":"Amélioration itérative du produit","effort":"Faible","priorite":"haute"},
    {"suggestion":"Documenter les processus clés et les décisions architecturales.","impact":"Transfert de connaissances facilité","effort":"Faible","priorite":"moyenne"},
    {"suggestion":"Planifier des sprints courts (2 semaines) avec revues régulières.","impact":"Agilité et réactivité","effort":"Faible","priorite":"haute"},
    {"suggestion":"Identifier et contacter 5 bêta-testeurs potentiels.","impact":"Validation rapide du concept","effort":"Faible","priorite":"haute"},
    {"suggestion":"Protéger la propriété intellectuelle (dépôt de marque, brevet).","impact":"Avantage concurrentiel défendable","effort":"Moyen","priorite":"haute"},
]

PROJETS_SIMILAIRES_DB: dict[str, list[dict]] = {
    "tech": [
        {"nom":"TechFlow SaaS","similitude":85,"forces":["Scalabilité","UX intuitive","API robuste"],"faiblesses":["Coût d'acquisition élevé","Dépendance cloud"],"lecon":"Démarrer local puis scaler progressivement"},
        {"nom":"DataPulse AI","similitude":78,"forces":["IA performante","Croissance rapide"],"faiblesses":["Complexité technique","Besoin en données"],"lecon":"Investir tôt dans la qualité des données"},
        {"nom":"CloudNex Platform","similitude":72,"forces":["Modèle freemium efficace","Communauté active"],"faiblesses":["Monétisation lente","Support coûteux"],"lecon":"Construire une communauté avant de monétiser"},
    ],
    "sante": [
        {"nom":"Doctolib","similitude":82,"forces":["Prise de RDV simple","Base patients large"],"faiblesses":["Dépendance médecins","Réglementation stricte"],"lecon":"Les partenariats médicaux sont essentiels"},
        {"nom":"Qare Télémédecine","similitude":75,"forces":["Téléconsultation fluide","Disponibilité 24/7"],"faiblesses":["Qualité vidéo variable","Couverture limitée"],"lecon":"La fiabilité technique est critique en santé"},
    ],
    "finance": [
        {"nom":"Revolut Lite","similitude":80,"forces":["Interface utilisateur","Multi-devises"],"faiblesses":["Régulations bancaires","Confiance initiale"],"lecon":"Obtenir les licences avant de lancer"},
        {"nom":"BudgetWise App","similitude":73,"forces":["Gestion budget intuitive","Gamification"],"faiblesses":["Rétention difficile","Modèle freemium"],"lecon":"La rétention est le vrai défi"},
    ],
    "education": [
        {"nom":"LearnHub Academy","similitude":80,"forces":["Contenu structuré","Certifications"],"faiblesses":["Production contenu coûteuse","Engagement variable"],"lecon":"Le contenu de qualité est roi"},
        {"nom":"SkillBridge","similitude":70,"forces":["Apprentissage adaptatif","Mentorat"],"faiblesses":["Scalabilité mentors","Coûts opérationnels"],"lecon":"Automatiser ce qui peut l'être"},
    ],
    "environnement": [{"nom":"GreenTech Solutions","similitude":78,"forces":["Impact mesurable","Subventions"],"faiblesses":["ROI lent","Technologie immature"],"lecon":"Les subventions accélèrent la croissance"}],
    "commerce": [{"nom":"ShopLocal Market","similitude":77,"forces":["Proximité client","Logistique optimisée"],"faiblesses":["Marges faibles","Concurrence Amazon"],"lecon":"Se différencier par l'expérience client"}],
    "social": [{"nom":"CommUnity Hub","similitude":75,"forces":["Engagement fort","Croissance organique"],"faiblesses":["Monétisation délicate","Modération complexe"],"lecon":"Définir les règles communautaires dès le départ"}],
    "culturel": [{"nom":"ArtStream Online","similitude":70,"forces":["Large audience","Diversité de contenus"],"faiblesses":["Droits d'auteur complexes","Marges faibles"],"lecon":"Négocier les droits tôt avec les créateurs"}],
    "agriculture": [{"nom":"AgriSense","similitude":74,"forces":["Données temps réel","Réduction des intrants"],"faiblesses":["Coût capteurs","Connectivité rurale"],"lecon":"Partir sur une zone pilote limitée"}],
    "transport": [{"nom":"LogiTrack","similitude":76,"forces":["Optimisation routes","Dashboard temps réel"],"faiblesses":["Intégration legacy","Adoption chauffeurs"],"lecon":"Impliquer les utilisateurs finaux dès le design"}],
}

MARKET_SIZES: dict[str, dict] = {
    "tech":          {"tam":"500 Mrd USD","sam":"50 Mrd USD","som":"5 Mrd USD","croissance":"12%"},
    "sante":         {"tam":"300 Mrd USD","sam":"30 Mrd USD","som":"3 Mrd USD","croissance":"9%"},
    "finance":       {"tam":"400 Mrd USD","sam":"40 Mrd USD","som":"4 Mrd USD","croissance":"10%"},
    "education":     {"tam":"200 Mrd USD","sam":"20 Mrd USD","som":"2 Mrd USD","croissance":"15%"},
    "environnement": {"tam":"150 Mrd USD","sam":"15 Mrd USD","som":"1,5 Mrd USD","croissance":"18%"},
    "commerce":      {"tam":"600 Mrd USD","sam":"60 Mrd USD","som":"6 Mrd USD","croissance":"8%"},
    "social":        {"tam":"80 Mrd USD","sam":"8 Mrd USD","som":"800 M USD","croissance":"11%"},
    "culturel":      {"tam":"50 Mrd USD","sam":"5 Mrd USD","som":"500 M USD","croissance":"7%"},
    "agriculture":   {"tam":"90 Mrd USD","sam":"9 Mrd USD","som":"900 M USD","croissance":"8%"},
    "transport":     {"tam":"350 Mrd USD","sam":"35 Mrd USD","som":"3,5 Mrd USD","croissance":"9%"},
    "general":       {"tam":"100 Mrd USD","sam":"10 Mrd USD","som":"1 Mrd USD","croissance":"7%"},
}

SECTOR_COLORS: dict[str, dict] = {
    "tech":          {"primary":"#3b82f6","gradient":"linear-gradient(135deg, #1e3a8a, #3b82f6)"},
    "sante":         {"primary":"#10b981","gradient":"linear-gradient(135deg, #064e3b, #10b981)"},
    "finance":       {"primary":"#f59e0b","gradient":"linear-gradient(135deg, #78350f, #f59e0b)"},
    "education":     {"primary":"#8b5cf6","gradient":"linear-gradient(135deg, #4c1d95, #8b5cf6)"},
    "environnement": {"primary":"#22c55e","gradient":"linear-gradient(135deg, #14532d, #22c55e)"},
    "commerce":      {"primary":"#f97316","gradient":"linear-gradient(135deg, #7c2d12, #f97316)"},
    "social":        {"primary":"#ec4899","gradient":"linear-gradient(135deg, #831843, #ec4899)"},
    "culturel":      {"primary":"#a855f7","gradient":"linear-gradient(135deg, #581c87, #a855f7)"},
    "agriculture":   {"primary":"#65a30d","gradient":"linear-gradient(135deg, #365314, #65a30d)"},
    "transport":     {"primary":"#0ea5e9","gradient":"linear-gradient(135deg, #0c4a6e, #0ea5e9)"},
    "general":       {"primary":"#6366f1","gradient":"linear-gradient(135deg, #312e81, #6366f1)"},
}

COST_MULTIPLIERS: dict[str, float] = {
    "tech":1.2,"sante":1.5,"finance":1.4,"education":0.9,
    "environnement":1.1,"commerce":1.0,"social":0.9,"culturel":0.9,
    "agriculture":1.0,"transport":1.1,
}

SECTOR_HOOKS: dict[str, str] = {
    "tech":          "Dans un monde de plus en plus digital",
    "sante":         "Alors que les systèmes de santé sont sous pression",
    "finance":       "À l'heure de la révolution fintech",
    "education":     "Face aux défis de l'éducation moderne",
    "environnement": "Devant l'urgence climatique",
    "commerce":      "Dans un marché en constante évolution",
    "social":        "À l'ère de l'hyper-connexion",
    "culturel":      "Quand la culture cherche de nouveaux espaces d'expression",
    "agriculture":   "Dans un secteur agricole en pleine transformation numérique",
    "transport":     "Face aux enjeux de mobilité durable du XXIe siècle",
}

KPI_PAR_SECTEUR: dict[str, list[dict]] = {
    "tech": [
        {"kpi":"Monthly Active Users (MAU)","cible":"> 1 000 à 6 mois","frequence":"Mensuel"},
        {"kpi":"Taux de rétention J30","cible":"> 40%","frequence":"Mensuel"},
        {"kpi":"Temps de réponse API","cible":"< 200ms (p95)","frequence":"Continu"},
        {"kpi":"NPS (Net Promoter Score)","cible":"> 40","frequence":"Trimestriel"},
        {"kpi":"MRR (Monthly Recurring Revenue)","cible":"Croissance +20%/mois","frequence":"Mensuel"},
        {"kpi":"Taux de conversion trial→payant","cible":"> 15%","frequence":"Mensuel"},
        {"kpi":"Disponibilité (uptime)","cible":"> 99,9%","frequence":"Continu"},
    ],
    "sante": [
        {"kpi":"Patients actifs par mois","cible":"> 500 à 6 mois","frequence":"Mensuel"},
        {"kpi":"Taux de satisfaction patient","cible":"> 4,5/5","frequence":"Post-consultation"},
        {"kpi":"Délai moyen de prise en charge","cible":"< 24h","frequence":"Hebdomadaire"},
        {"kpi":"Incidents de sécurité données","cible":"0","frequence":"Continu"},
        {"kpi":"Taux d'adhésion traitement","cible":"> 80%","frequence":"Mensuel"},
    ],
    "finance": [
        {"kpi":"Volume de transactions","cible":"+30%/mois","frequence":"Mensuel"},
        {"kpi":"Taux de fraude détectée","cible":"< 0,1%","frequence":"Continu"},
        {"kpi":"Coût par acquisition (CPA)","cible":"< 50 TND","frequence":"Mensuel"},
        {"kpi":"Churn rate","cible":"< 5%/mois","frequence":"Mensuel"},
        {"kpi":"Revenus de commission","cible":"Croissance +25%/T","frequence":"Trimestriel"},
    ],
    "education": [
        {"kpi":"Taux de complétion de cours","cible":"> 60%","frequence":"Hebdomadaire"},
        {"kpi":"Apprenants actifs","cible":"> 500 à 3 mois","frequence":"Hebdomadaire"},
        {"kpi":"Score moyen aux évaluations","cible":"> 75%","frequence":"Par cours"},
        {"kpi":"NPS apprenants","cible":"> 50","frequence":"Trimestriel"},
        {"kpi":"Taux de certification","cible":"> 40%","frequence":"Mensuel"},
    ],
    "general": [
        {"kpi":"Utilisateurs actifs mensuels","cible":"Croissance +20%/mois","frequence":"Mensuel"},
        {"kpi":"Taux de rétention","cible":"> 50%","frequence":"Mensuel"},
        {"kpi":"NPS","cible":"> 35","frequence":"Trimestriel"},
        {"kpi":"Revenus mensuels","cible":"Croissance positive","frequence":"Mensuel"},
        {"kpi":"Taux de conversion","cible":"> 5%","frequence":"Hebdomadaire"},
    ],
}

PERSONAS_PAR_SECTEUR: dict[str, list[dict]] = {
    "tech": [
        {"nom":"Alex, le Développeur Early Adopter","age":"28-35 ans","role":"Développeur / Tech Lead",
         "motivations":["Automatiser ses tâches","Rester à la pointe","Gagner du temps"],
         "frustrations":["Outils trop complexes","Documentation insuffisante","Bugs non résolus"],
         "canaux":["GitHub","Product Hunt","Twitter/X","Newsletters tech"],
         "citation":"Je veux une API propre et bien documentée, pas une usine à gaz."},
        {"nom":"Marie, la CTO Pragmatique","age":"35-45 ans","role":"CTO / Directrice Technique",
         "motivations":["ROI mesurable","Équipe productive","Réduction des coûts"],
         "frustrations":["Vendor lock-in","Manque de support","Sécurité non garantie"],
         "canaux":["LinkedIn","Conférences tech","Recommandations pairs"],
         "citation":"Montrez-moi les métriques et la roadmap avant de me vendre quoi que ce soit."},
    ],
    "sante": [
        {"nom":"Dr. Karim, le Médecin Connecté","age":"38-50 ans","role":"Médecin généraliste / Spécialiste",
         "motivations":["Améliorer le suivi patient","Réduire la charge administrative"],
         "frustrations":["Outils non intégrés","Manque de temps","Conformité RGPD complexe"],
         "canaux":["Congrès médicaux","Revues spécialisées","Bouche-à-oreille"],
         "citation":"Cet outil doit m'aider, pas m'ajouter du travail."},
        {"nom":"Leila, la Patiente Digitale","age":"25-40 ans","role":"Patiente / Utilisatrice finale",
         "motivations":["Accès rapide aux soins","Suivi de santé simplifié"],
         "frustrations":["Délais d'attente","Manque de transparence","Interfaces complexes"],
         "canaux":["Applications mobiles","Réseaux sociaux","Avis en ligne"],
         "citation":"Je veux un rendez-vous en 2 clics, pas en 20 minutes."},
    ],
    "general": [
        {"nom":"Sami, l'Entrepreneur Pragmatique","age":"30-42 ans","role":"Fondateur / Entrepreneur",
         "motivations":["Croissance rapide","Validation du marché","Rentabilité"],
         "frustrations":["Ressources limitées","Incertitude marché","Recrutement difficile"],
         "canaux":["LinkedIn","Podcast business","Réseaux d'entrepreneurs"],
         "citation":"Je n'ai pas de temps à perdre — montrez-moi ce que ça résout concrètement."},
        {"nom":"Nour, l'Utilisatrice Quotidienne","age":"22-35 ans","role":"Utilisatrice finale / Décideuse",
         "motivations":["Facilité d'utilisation","Gain de temps","Fiabilité"],
         "frustrations":["Interfaces complexes","Support inexistant","Prix opaques"],
         "canaux":["Instagram","Recommandations","App stores"],
         "citation":"Si je dois lire un manuel pour utiliser votre produit, vous avez échoué."},
    ],
}

ROADMAP_TEMPLATES: dict[str, list[dict]] = {
    "Idée préliminaire": [
        {"phase":"Q1","titre":"Idéation & Validation","budget_pct":10,
         "objectifs":["Définir le problème précis","20 interviews utilisateurs","Étude de marché initiale"],
         "livrables":["Brief projet","Personas utilisateurs","Rapport marché"],"risques":["Pivot nécessaire selon les retours"]},
        {"phase":"Q2","titre":"MVP Design","budget_pct":20,
         "objectifs":["Créer les wireframes","Choisir la stack technique","Constituer l'équipe core"],
         "livrables":["Maquettes UI","Architecture technique","Équipe fondatrice"],"risques":["Recrutement difficile"]},
        {"phase":"Q3","titre":"Développement MVP","budget_pct":40,
         "objectifs":["Développer les features essentielles","Tester avec 10 bêta-users","Itérer"],
         "livrables":["MVP fonctionnel","Rapport bêta-test"],"risques":["Scope creep","Retards techniques"]},
        {"phase":"Q4","titre":"Lancement & Croissance","budget_pct":30,
         "objectifs":["Lancement public","Premiers 100 utilisateurs","Préparer levée de fonds"],
         "livrables":["Produit en production","Dashboard métriques","Pitch deck investisseurs"],"risques":["Adoption lente","Concurrence"]},
    ],
    "Phase MVP / Prototype": [
        {"phase":"Q1","titre":"Finalisation MVP","budget_pct":25,
         "objectifs":["Corriger les bugs critiques","Améliorer l'UX","Automatiser les tests"],
         "livrables":["MVP stable v1.0","Suite de tests"],"risques":["Dette technique"]},
        {"phase":"Q2","titre":"Bêta publique","budget_pct":25,
         "objectifs":["Recruter 50 bêta-testeurs","Mesurer NPS","Prioriser le backlog"],
         "livrables":["Programme bêta","NPS > 30","Backlog priorisé"],"risques":["Feedbacks contradictoires"]},
        {"phase":"Q3","titre":"Lancement officiel","budget_pct":30,
         "objectifs":["Lancement marketing","Objectif 200 users","Premier revenu"],
         "livrables":["Stratégie go-to-market","Page pricing","Premières ventes"],"risques":["CAC élevé"]},
        {"phase":"Q4","titre":"Scale","budget_pct":20,
         "objectifs":["Atteindre 1 000 utilisateurs","MRR positif","Lever des fonds"],
         "livrables":["Métriques croissance","Deck Series A"],"risques":["Churn","Financement"]},
    ],
}

FIELD_LIMITS = {
    "titre":200,"description":5000,"objectifs":2000,"secteur":50,
    "budget":100,"duree":10,"equipe":10,"contenu":50_000,
    "mots_cles":500,"type_analyse":100,
}

ERROR_TYPES = {400:"Requête invalide",401:"Non autorisé",403:"Accès refusé",
               422:"Données non traitables",429:"Trop de requêtes",500:"Erreur serveur interne"}

# JSON Schema pour validation stricte
_PROJECT_SCHEMA = {
    "type": "object",
    "properties": {
        "titre": {"type": "string", "minLength": 1, "maxLength": 200},
        "description": {"type": "string", "maxLength": 5000},
        "objectifs": {"type": "string", "maxLength": 2000},
        "secteur": {"type": "string", "maxLength": 50},
    },
    "required": ["titre"],
    "additionalProperties": True,
}

# ─────────────────────────────────────────────────────────────────────────────
# DATACLASSES
# ─────────────────────────────────────────────────────────────────────────────
@dataclass
class ProjectInput:
    titre: str
    description: str = ""
    objectifs: str = ""
    secteur: str = ""
    tags: list[str] = field(default_factory=list)

    @classmethod
    def from_dict(cls, data: dict) -> tuple["ProjectInput | None", Any]:
        titre = str(data.get("titre", "")).strip()
        if not titre:
            return None, _err("Le champ 'titre' est requis.", 422, "Fournissez un titre de projet non vide.")
        if len(titre) > FIELD_LIMITS["titre"]:
            return None, _err(f"'titre' dépasse {FIELD_LIMITS['titre']} caractères.", 422)
        description = str(data.get("description", "")).strip()
        if len(description) > FIELD_LIMITS["description"]:
            return None, _err(f"'description' dépasse {FIELD_LIMITS['description']} caractères.", 422)
        objectifs = str(data.get("objectifs", "")).strip()
        if len(objectifs) > FIELD_LIMITS["objectifs"]:
            return None, _err(f"'objectifs' dépasse {FIELD_LIMITS['objectifs']} caractères.", 422)
        secteur = str(data.get("secteur", "")).strip().lower()
        tags = [str(t) for t in data.get("tags", []) if t]
        return cls(titre=titre, description=description, objectifs=objectifs, secteur=secteur, tags=tags), None

    def cache_key(self) -> str:
        h = hashlib.md5(f"{self.titre}{self.description}{self.secteur}{self.objectifs}".encode()).hexdigest()
        return h

# ─────────────────────────────────────────────────────────────────────────────
# HELPERS
# ─────────────────────────────────────────────────────────────────────────────
def _ok(result: Any, **extra) -> Any:
    payload: dict = {
        "success": True, "result": result,
        "timestamp": datetime.now().isoformat(), "version": "5.0.0",
    }
    payload.update(extra)
    return jsonify(payload)


def _err(message: str, code: int = 400, hint: str = "") -> Any:
    logger.warning("API [%d] %s", code, message)
    return jsonify({
        "success": False,
        "error": {"code": code, "type": ERROR_TYPES.get(code, "Erreur"), "message": message,
                  "hint": hint or "Consultez GET /api/health pour la documentation."},
        "timestamp": datetime.now().isoformat(),
    }), code


def _get_json_or_error() -> tuple[dict | None, Any]:
    if not request.is_json:
        return None, _err("Content-Type doit être application/json.", 400)
    data = request.get_json(silent=True)
    if not data:
        return None, _err("Corps JSON invalide ou vide.", 400)
    # Validation stricte avec jsonschema si disponible
    if _JSONSCHEMA:
        try:
            _jschema_validate(data, _PROJECT_SCHEMA)
        except _JValidationError as e:
            return None, _err(f"Validation échouée : {e.message}", 422)
    return data, None


def _require_fields(data: dict, *fields: str) -> Any | None:
    missing = [f for f in fields if not str(data.get(f, "")).strip()]
    if missing:
        return _err(f"Champs requis manquants : {', '.join(missing)}", 422,
                    f"Fournissez des valeurs non vides pour : {', '.join(missing)}")
    return None


def _safe(value: str) -> str:
    return str(escape(value))


def _normalize(text: str) -> str:
    return unicodedata.normalize("NFKD", text).encode("ascii", "ignore").decode()


def truncate_word(text: str, limit: int) -> str:
    if len(text) <= limit:
        return text
    return text[:limit].rsplit(" ", 1)[0].rstrip(".,;:") + "…"


def _roi_deterministe(titre: str, sect: str) -> int:
    rng = random.Random(hash(f"{titre}{sect}") & 0xFFFFFFFF)
    return rng.randint(150, 350)


def _secteur_from_data(data: dict) -> str:
    s = str(data.get("secteur", "")).strip().lower()
    if s:
        return s
    titre = data.get('titre', '')
    description = data.get('description', '')
    objectifs = data.get('objectifs', '')
    full = f"{titre} {description} {objectifs}"
    return detecter_secteur(_normalize(full.strip()))


def _cache_get(key: str) -> Any | None:
    with _cache_lock:
        return _analysis_cache.get(key)


def _cache_set(key: str, value: Any) -> None:
    with _cache_lock:
        _analysis_cache[key] = value


def _fire_webhook(event: str, payload: dict) -> None:
    """Lance les webhooks enregistrés de manière asynchrone."""
    with _webhooks_lock:
        urls = list(_webhooks.get(event, []))
    if not urls:
        return
    import urllib.request
    body = json.dumps({"event": event, "data": payload, "timestamp": datetime.now().isoformat()}).encode()
    for url in urls:
        try:
            req = urllib.request.Request(url, data=body,
                                         headers={"Content-Type": "application/json"}, method="POST")
            urllib.request.urlopen(req, timeout=5)
        except Exception as e:
            logger.warning("Webhook %s failed: %s", url, e)


# ─────────────────────────────────────────────────────────────────────────────
# LLM HELPER
# ─────────────────────────────────────────────────────────────────────────────
def _llm_complete(system: str, user: str, max_tokens: int = 800, temperature: float = 0.7) -> str | None:
    """Appelle le LLM configuré avec circuit breaker. Retourne None si indisponible."""
    if not _LLM_AVAILABLE or _llm_client is None:
        return None
    def _call():
        resp = _llm_client.chat.completions.create(
            model=_LLM_MODEL,
            messages=[{"role":"system","content":system},{"role":"user","content":user}],
            max_tokens=max_tokens,
            temperature=temperature,
        )
        return resp.choices[0].message.content.strip()
    try:
        return _llm_breaker.call(_call)
    except Exception as e:
        logger.warning("LLM call failed: %s", e)
        return None
                                       

def _llm_stream(system: str, user: str, max_tokens: int = 1000) -> Generator[str, None, None]:
    """Génère un stream SSE depuis le LLM."""
    if not _LLM_AVAILABLE or _llm_client is None:
        yield "data: [LLM non configuré — ajoutez OPENAI_API_KEY ou GROQ_API_KEY]\n\n"
        return
    try:
        stream = _llm_client.chat.completions.create(
            model=_LLM_MODEL,
            messages=[{"role":"system","content":system},{"role":"user","content":user}],
            max_tokens=max_tokens,
            stream=True,
        )
        for chunk in stream:
            delta = chunk.choices[0].delta.content
            if delta:
                yield f"data: {json.dumps({'token': delta})}\n\n"
        yield "data: [DONE]\n\n"
    except Exception as e:
        yield f"data: {json.dumps({'error': str(e)})}\n\n"


# ─────────────────────────────────────────────────────────────────────────────
# NLP FUNCTIONS
# ─────────────────────────────────────────────────────────────────────────────
MOTS_POSITIFS_FR = {
    "innovant","revolutionnaire","excellent","performant","unique","efficace","optimal",
    "croissance","succes","solution","ameliore","rapide","fiable","simple","puissant",
    "rentable","durable","scalable","disruptif","agile",
}
MOTS_NEGATIFS_FR = {
    "probleme","risque","difficile","complexe","echec","perte","lent","couteux",
    "impossible","limite","manque","absence","retard","insuffisant",
}


@cached(_sentiment_cache, lock=_sentiment_lock)
def analyser_sentiment(texte: str) -> tuple[str, float]:
    normalized = _normalize(texte.lower())
    mots = set(normalized.split())
    pos = len(mots & MOTS_POSITIFS_FR)
    neg = len(mots & MOTS_NEGATIFS_FR)
    blob_score = TextBlob(texte).sentiment.polarity
    nb_mots = max(len(mots), 1)
    score = ((pos - neg) / nb_mots) * 2 + blob_score * 0.3
    score = round(max(-1.0, min(1.0, score)), 3)
    if score > 0.15:   return "Très positif", score
    elif score > 0.0:  return "Positif", score
    elif score > -0.1: return "Neutre", score
    else:              return "Négatif", score


@lru_cache(maxsize=512)
def detecter_secteur(texte: str) -> str:
    texte_n = _normalize(texte.lower())
    scores: dict[str, int] = {s: 0 for s in SECTEURS_KEYWORDS}
    premium = {"ia","blockchain","fintech","biotech","saas","cleantech","edtech","agritech"}
    for secteur, mots in SECTEURS_KEYWORDS.items():
        for mot in mots:
            if mot in texte_n:
                scores[secteur] += 2 if mot in premium else 1
    best = max(scores, key=scores.get)
    return best if scores[best] > 0 else "general"


def extraire_mots_cles(texte: str, n: int = 8) -> list[str]:
    stop = {
        "le","la","les","de","du","des","un","une","et","en","a","au","aux","est","sont",
        "pour","par","sur","avec","qui","que","dans","il","elle","nous","vous","ils","elles",
        "ce","cette","ces","son","sa","ses","mon","ma","mes","leur","leurs","tout","tous",
        "pas","plus","mais","ou","donc","car","ni","si","comme","etre","avoir","faire",
        "dire","aller","voir","aussi","tres","bien","peut","doit","projet","permettre",
        "mettre","notre","votre","entre","vers","dont","celui","celle",
    }
    mots = re.findall(r"\b[a-zA-ZÀ-ÿ]{4,}\b", texte.lower())
    filtered = [_normalize(m) for m in mots if _normalize(m) not in stop]
    return [w for w, _ in Counter(filtered).most_common(n)]


def evaluer_completude(titre: str, description: str, objectifs: str, secteur: str) -> tuple[int, dict]:
    score = 0
    details: dict[str, str] = {}
    if len(titre) > 5:   score += 10; details["titre"] = "Bon"
    elif len(titre) > 3: score += 5;  details["titre"] = "Moyen"
    else:                             details["titre"] = "À améliorer"
    mots_desc = len(description.split())
    if mots_desc > 100:  score += 30; details["description"] = "Très détaillée"
    elif mots_desc > 50: score += 20; details["description"] = "Détaillée"
    elif mots_desc > 20: score += 10; details["description"] = "Suffisante"
    else:                             details["description"] = "Trop courte"
    if objectifs and len(objectifs.split()) > 10:
        score += 20; details["objectifs"] = "Bien définis"
    elif objectifs:
        score += 10; details["objectifs"] = "À préciser"
    else:
        details["objectifs"] = "Manquants"
    if secteur: score += 10; details["secteur"] = "Spécifié"
    else:                    details["secteur"] = "Non spécifié"
    mots_innov = ["innovant","revolutionnaire","unique","disruptif","nouveau","original","brevet"]
    full_n = _normalize(f"{titre} {description} {objectifs}".lower())
    found = [m for m in mots_innov if m in full_n]
    if found:
        score += min(30, len(found) * 8)
        details["innovation"] = f"Détectée ({', '.join(found)})"
    else:
        details["innovation"] = "Non détectée"
    return min(100, score), details


def detecter_maturite(description: str, objectifs: str) -> str:
    t = _normalize(f"{description} {objectifs}".lower())
    if any(w in t for w in ("mvp","prototype","pilote","poc")):
        return "Phase MVP / Prototype"
    if any(w in t for w in ("etude","recherche","concept","ideation","idee")):
        return "Phase d'étude / Concept"
    if any(w in t for w in ("developpement","realisation","implementation")):
        return "Phase de développement"
    if any(w in t for w in ("lancement","commercialisation","go-to-market")):
        return "Phase de lancement"
    if any(w in t for w in ("scale","expansion","croissance","internationalisation")):
        return "Phase de croissance"
    return "Idée préliminaire"


# ─────────────────────────────────────────────────────────────────────────────
# AUTH
# ─────────────────────────────────────────────────────────────────────────────
def require_api_key(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        if API_KEY:
            key = request.headers.get("X-API-Key") or request.args.get("api_key")
            if key != API_KEY:
                return _err("Clé API invalide ou manquante.", 401, "Ajoutez X-API-Key dans vos headers.")
        return f(*args, **kwargs)
    return decorated


def cached_endpoint(ttl_override: int | None = None):
    """Décorateur de cache sur la réponse d'un endpoint."""
    def decorator(f):
        @wraps(f)
        def wrapper(*args, **kwargs):
            data = request.get_json(silent=True) or {}
            if not data.get("titre"):
                return f(*args, **kwargs)
            proj, _ = ProjectInput.from_dict(data)
            if not proj:
                return f(*args, **kwargs)
            cache_key = f"{f.__name__}:{proj.cache_key()}"
            cached = _cache_get(cache_key)
            if cached is not None:
                resp = jsonify(cached)
                resp.headers["X-Cache"] = "HIT"
                return resp
            response = f(*args, **kwargs)
            if hasattr(response, "get_json"):
                try:
                    payload = response.get_json()
                    if payload and payload.get("success"):
                        _cache_set(cache_key, payload)
                except:
                    pass
            return response
        return wrapper
    return decorator


# ─────────────────────────────────────────────────────────────────────────────
# ROUTES
# ─────────────────────────────────────────────────────────────────────────────
@app.route("/api/health", methods=["GET"])
def health():
    return jsonify({
        "status": "ok", "version": "5.0.0",
        "timestamp": datetime.now().isoformat(),
        "auth_enabled": bool(API_KEY),
        "llm_enabled": _LLM_AVAILABLE,
        "llm_model": _LLM_MODEL,
        "cache_size": len(_analysis_cache),
        "prometheus": _PROMETHEUS,
        "endpoints": {
            "GET":  ["/api/health", "/metrics"],
            "POST": [
                "/api/resumer", "/api/ameliorations", "/api/description",
                "/api/analyser", "/api/pitch", "/api/viabilite",
                "/api/comparer", "/api/swot", "/api/market", "/api/budget",
                "/api/export_pdf", "/api/pitch_deck", "/api/risques",
                "/api/site_web", "/api/roadmap", "/api/persona", "/api/kpi",
                "/api/batch", "/api/export_csv", "/api/webhook/register",
            ],
            "SSE": ["/api/stream/pitch", "/api/stream/analyse"],
        },
    })


@app.route("/metrics", methods=["GET"])
def metrics():
    if not _PROMETHEUS:
        return _err("Prometheus non installé.", 501, "pip install prometheus-client")
    return Response(generate_latest(), mimetype=CONTENT_TYPE_LATEST)


# ── /api/resumer ─────────────────────────────────────────────────────────────
@app.route("/api/resumer", methods=["POST"])
@require_api_key
@cached_endpoint()
def resumer():
    data, err = _get_json_or_error()
    if err: return err
    err = _require_fields(data, "titre", "description")
    if err: return err
    proj, err = ProjectInput.from_dict(data)
    if err: return err

    sect               = proj.secteur or detecter_secteur(_normalize(f"{proj.titre} {proj.description} {proj.objectifs}"))
    kw                 = extraire_mots_cles(f"{proj.titre} {proj.description} {proj.objectifs}", 6)
    sentiment, s_val   = analyser_sentiment(proj.description)
    score_c, details_c = evaluer_completude(proj.titre, proj.description, proj.objectifs, sect)
    maturite           = detecter_maturite(proj.description, proj.objectifs)

    # Enrichissement LLM si disponible
    llm_summary = _llm_complete(
        "Tu es un expert en analyse de projets d'entreprise. Réponds en français, de manière concise.",
        f"Résume en 3 phrases le projet suivant :\nTitre: {proj.titre}\nDescription: {proj.description}\nSecteur: {sect}",
        max_tokens=200,
    )

    resume = (
        f"=== RÉSUMÉ DU PROJET : {proj.titre.upper()} ===\n\n"
        f"Secteur détecté   : {sect.capitalize()}\n"
        f"Maturité           : {maturite}\n"
        f"Score complétude   : {score_c}/100\n"
        f"Sentiment global   : {sentiment} ({s_val})\n"
        f"Mots-clés          : {', '.join(kw)}\n\n"
    )
    if llm_summary:
        resume += f"--- Résumé IA ---\n{llm_summary}\n\n"
    resume += (
        f"--- Description ---\n{truncate_word(proj.description, 300)}\n\n"
        f"--- Objectifs ---\n{proj.objectifs if proj.objectifs else 'Non spécifiés'}\n\n"
        f"--- Détails complétude ---\n"
    )
    for k, v in details_c.items():
        resume += f"  {k.capitalize():20s} : {v}\n"

    return _ok(resume, metadata={
        "secteur": sect, "score": score_c, "sentiment": sentiment,
        "score_sentiment": s_val, "maturite": maturite, "mots_cles": kw,
        "details_completude": details_c, "llm_enrichi": llm_summary is not None,
    })


# ── /api/ameliorations ───────────────────────────────────────────────────────
@app.route("/api/ameliorations", methods=["POST"])
@require_api_key
def ameliorations():
    data, err = _get_json_or_error()
    if err: return err
    err = _require_fields(data, "titre")
    if err: return err
    proj, err = ProjectInput.from_dict(data)
    if err: return err

    sect     = proj.secteur or _secteur_from_data(data)
    specifics = list(AMELIORATIONS_PAR_SECTEUR.get(sect, []))
    pool      = specifics + list(AMELIORATIONS_GENERIQUES)
    suggestions = random.sample(pool, min(6, len(pool)))

    text = f"=== AMÉLIORATIONS SUGGÉRÉES : {proj.titre.upper()} ===\nSecteur : {sect.capitalize()}\n\n"
    for i, s in enumerate(suggestions, 1):
        text += (
            f"{i}. {s['suggestion']}\n"
            f"   Impact   : {s['impact']}\n"
            f"   Effort   : {s['effort']}\n"
            f"   Priorité : {s['priorite'].upper()}\n\n"
        )

    return _ok(text, suggestions=suggestions)


# ── /api/description ─────────────────────────────────────────────────────────
@app.route("/api/description", methods=["POST"])
@require_api_key
def generer_description():
    data, err = _get_json_or_error()
    if err: return err
    err = _require_fields(data, "titre")
    if err: return err
    proj, err = ProjectInput.from_dict(data)
    if err: return err

    mots_cles = str(data.get("mots_cles", "")).strip()
    sect      = proj.secteur or _secteur_from_data(data)

    # Essayer LLM d'abord
    llm_desc = _llm_complete(
        "Tu es un expert en création de descriptions de projets startup. Réponds en français.",
        f"Écris une description professionnelle et percutante (3-4 phrases) pour ce projet :\nTitre: {proj.titre}\nSecteur: {sect}\nObjectifs: {proj.objectifs}\nMots-clés: {mots_cles}",
        max_tokens=300,
    )

    if llm_desc:
        desc = llm_desc
    else:
        desc = (
            f"{proj.titre} est un projet innovant dans le domaine {sect} "
            f"qui vise à transformer la manière dont les utilisateurs interagissent "
            f"avec les solutions existantes. "
        )
        if proj.objectifs:
            desc += f"Les objectifs principaux incluent : {proj.objectifs}. "
        desc += (
            f"Ce projet se distingue par son approche centrée sur l'utilisateur "
            f"et son potentiel de croissance dans un marché en pleine expansion."
        )
        if mots_cles:
            desc += f" Les technologies clés incluent : {mots_cles}."

    return _ok(desc, metadata={"secteur": sect, "llm_enrichi": llm_desc is not None})


# ── /api/analyser ────────────────────────────────────────────────────────────
@app.route("/api/analyser", methods=["POST"])
@require_api_key
@cached_endpoint()
def analyser():
    data, err = _get_json_or_error()
    if err: return err
    err = _require_fields(data, "titre", "description")
    if err: return err
    proj, err = ProjectInput.from_dict(data)
    if err: return err

    full   = f"{proj.titre} {proj.description} {proj.objectifs}"
    sect   = proj.secteur or detecter_secteur(_normalize(full.strip()))
    kw     = extraire_mots_cles(full, 10)
    sent, s_val   = analyser_sentiment(proj.description)
    score_c, dtls  = evaluer_completude(proj.titre, proj.description, proj.objectifs, sect)
    maturite       = detecter_maturite(proj.description, proj.objectifs)
    full_n         = _normalize(full.lower())
    ms             = MARKET_SIZES.get(sect, MARKET_SIZES["general"])

    forces = [f"Expertise identifiée en {m}" for m in kw[:3]]
    if score_c > 70: forces.append("Projet bien documenté")

    faiblesses = []
    if "budget" not in full_n:     faiblesses.append("Détails financiers manquants")
    if "equipe" not in full_n:     faiblesses.append("Équipe non mentionnée")
    if score_c < 50:               faiblesses.append("Description insuffisamment détaillée")
    if "concurrent" not in full_n: faiblesses.append("Analyse concurrentielle absente")

    opportunites = [
        f"Marché {sect} en croissance ({ms['croissance']}/an)",
        "Demande utilisateur croissante en solutions innovantes",
        f"Marché total adressable : {ms['tam']}",
    ]
    menaces = [
        "Concurrence établie sur le segment",
        "Évolution réglementaire potentielle",
        "Risque de changement technologique rapide",
    ]

    return _ok(
        f"Analyse complète de '{proj.titre}' — Score : {score_c}/100 — Secteur : {sect}",
        metadata={
            "titre": proj.titre, "secteur_detecte": sect,
            "score_completude": score_c, "details_completude": dtls,
            "mots_cles": kw, "sentiment": sent, "score_sentiment": s_val,
            "maturite": maturite,
            "swot": {"forces": forces, "faiblesses": faiblesses,
                     "opportunites": opportunites, "menaces": menaces},
        },
    )


# ── /api/pitch ───────────────────────────────────────────────────────────────
@app.route("/api/pitch", methods=["POST"])
@require_api_key
def generer_pitch():
    data, err = _get_json_or_error()
    if err: return err
    err = _require_fields(data, "titre", "description")
    if err: return err
    proj, err = ProjectInput.from_dict(data)
    if err: return err

    sect         = proj.secteur or _secteur_from_data(data)
    sentiment, _ = analyser_sentiment(proj.description)
    maturite     = detecter_maturite(proj.description, proj.objectifs)
    hook         = SECTOR_HOOKS.get(sect, "Face aux défis actuels")
    ms           = MARKET_SIZES.get(sect, MARKET_SIZES["general"])

    llm_pitch = _llm_complete(
        "Tu es un expert en pitch startup pour des investisseurs. Réponds en français. Sois percutant et concis.",
        f"Crée un pitch investisseur structuré (Problème / Solution / Marché / Avantage compétitif / Appel à l'action) pour :\nProjet: {proj.titre}\nDescription: {proj.description}\nSecteur: {sect}\nObjectifs: {proj.objectifs}",
        max_tokens=600,
    )

    if llm_pitch:
        pitch = llm_pitch
    else:
        pitch = (
            f"{hook}, {proj.titre} propose une solution qui répond à un besoin réel.\n\n"
            f"PROBLÈME : Les solutions actuelles dans le secteur {sect} manquent "
            f"d'innovation et ne répondent pas aux attentes des utilisateurs modernes.\n\n"
            f"SOLUTION : {truncate_word(proj.description, 200)}\n\n"
            f"MARCHÉ : Le secteur {sect} représente un TAM de {ms['tam']} "
            f"avec une croissance annuelle de {ms['croissance']}.\n\n"
            f"AVANTAGE COMPÉTITIF : Notre approche se distingue par son focus "
            f"sur l'expérience utilisateur et l'utilisation de technologies de pointe.\n\n"
        )
        if proj.objectifs:
            pitch += f"OBJECTIFS : {proj.objectifs}\n\n"
        pitch += f"PHASE ACTUELLE : {maturite}\n"

    return _ok(pitch, metadata={"sentiment": sentiment, "maturite": maturite, "secteur": sect, "llm_enrichi": llm_pitch is not None})


# ── SSE STREAMING /api/stream/pitch ──────────────────────────────────────────
@app.route("/api/stream/pitch", methods=["POST"])
@require_api_key
def stream_pitch():
    """Streaming SSE du pitch en temps réel via LLM."""
    data, err = _get_json_or_error()
    if err: return err
    err = _require_fields(data, "titre", "description")
    if err: return err
    proj, _ = ProjectInput.from_dict(data)
    if not proj: return _err("Données invalides", 422)

    sect = proj.secteur or _secteur_from_data(data)
    system = "Tu es un expert en pitch startup. Génère un pitch percutant en français."
    user   = f"Pitch pour : {proj.titre} ({sect}) — {proj.description}"

    return Response(
        stream_with_context(_llm_stream(system, user)),
        mimetype="text/event-stream",
        headers={
            "Cache-Control": "no-cache",
            "X-Accel-Buffering": "no",
            "Connection": "keep-alive",
        },
    )


# ── SSE STREAMING /api/stream/analyse ────────────────────────────────────────
@app.route("/api/stream/analyse", methods=["POST"])
@require_api_key
def stream_analyse():
    """Streaming SSE de l'analyse complète en temps réel."""
    data, err = _get_json_or_error()
    if err: return err
    proj, err = ProjectInput.from_dict(data)
    if err: return err

    sect = proj.secteur or _secteur_from_data(data)
    system = "Tu es un analyste business senior. Fournis une analyse détaillée en français."
    user   = f"Analyse complète du projet '{proj.titre}' dans le secteur {sect}.\nDescription: {proj.description}\nObjectifs: {proj.objectifs}"

    return Response(
        stream_with_context(_llm_stream(system, user, max_tokens=1200)),
        mimetype="text/event-stream",
        headers={
            "Cache-Control": "no-cache",
            "X-Accel-Buffering": "no",
            "Connection": "keep-alive",
        },
    )


# ── /api/viabilite ───────────────────────────────────────────────────────────
@app.route("/api/viabilite", methods=["POST"])
@require_api_key
def analyser_viabilite():
    data, err = _get_json_or_error()
    if err: return err
    err = _require_fields(data, "titre", "description")
    if err: return err
    proj, err = ProjectInput.from_dict(data)
    if err: return err

    budget = str(data.get("budget", "")).strip()
    full_n = _normalize(f"{proj.titre} {proj.description} {proj.objectifs}".lower())
    sect   = proj.secteur or _secteur_from_data(data)
    score_c, details_c = evaluer_completude(proj.titre, proj.description, proj.objectifs, sect)

    risques = []
    if "concurrent" not in full_n:
        risques.append({"type":"Marché","description":"Analyse concurrentielle manquante","niveau":"Élevé"})
    if "brevet" not in full_n and sect in ("tech","sante"):
        risques.append({"type":"Propriété intellectuelle","description":"Protection non abordée","niveau":"Moyen"})
    if not budget:
        risques.append({"type":"Financier","description":"Budget non défini","niveau":"Élevé"})
    if "equipe" not in full_n and "team" not in full_n:
        risques.append({"type":"Équipe","description":"Composition d'équipe non mentionnée","niveau":"Moyen"})

    forces = []
    if score_c > 70: forces.append("Projet bien documenté")
    if any(w in full_n for w in ("equipe","team")): forces.append("Équipe mentionnée")
    if any(w in full_n for w in ("client","utilisateur","user")): forces.append("Focus utilisateur identifié")
    if any(w in full_n for w in ("innovant","unique","disruptif")): forces.append("Innovation détectée")

    reco = "Projet viable — Bon potentiel" if score_c > 60 else "À améliorer — Complétez les informations"
    niv  = "Faible" if len(risques) < 2 else ("Moyen" if len(risques) < 4 else "Élevé")

    text = (
        f"=== ANALYSE DE VIABILITÉ : {proj.titre.upper()} ===\n\n"
        f"Score complétude : {score_c}/100\nRecommandation   : {reco}\nNiveau de risque : {niv}\n\n"
        f"--- Forces ---\n"
    )
    for f_ in forces: text += f"  + {f_}\n"
    text += "\n--- Risques identifiés ---\n"
    for r in risques: text += f"  ! [{r['niveau']}] {r['type']} : {r['description']}\n"

    return _ok(text, metadata={
        "score_completude": score_c, "details_completude": details_c,
        "forces": forces, "risques": risques,
        "recommandation": reco, "niveau_risque": niv, "secteur": sect,
    })


# ── /api/comparer ─────────────────────────────────────────────────────────────
@app.route("/api/comparer", methods=["POST"])
@require_api_key
def comparer_projets():
    data, err = _get_json_or_error()
    if err: return err
    err = _require_fields(data, "titre")
    if err: return err
    proj, err = ProjectInput.from_dict(data)
    if err: return err

    sect       = proj.secteur or _secteur_from_data(data)
    page       = max(1, int(data.get("page", 1)))
    per_page   = min(10, max(1, int(data.get("per_page", 3))))
    similaires_all = PROJETS_SIMILAIRES_DB.get(sect) or [
        {"nom":"Projet de référence générique","similitude":55,"forces":["À identifier selon le secteur"],
         "faiblesses":["Données sectorielles insuffisantes"],"lecon":"Réaliser une étude de marché ciblée"},
    ]
    total = len(similaires_all)
    start = (page - 1) * per_page
    similaires = similaires_all[start:start + per_page]

    kw   = extraire_mots_cles(proj.description, 4)
    diff = f"Différenciation possible sur : {', '.join(kw[:3])}" if kw else "innovation, expérience utilisateur"

    return _ok({
        "votre_projet": {"nom": proj.titre, "secteur": sect, "description_courte": truncate_word(proj.description, 150)},
        "similaires": similaires,
        "differenciateur": diff,
        "positionnement": diff,
        "pagination": {"page": page, "per_page": per_page, "total": total, "pages": -(-total // per_page)},
    })


# ── /api/swot ────────────────────────────────────────────────────────────────
@app.route("/api/swot", methods=["POST"])
@require_api_key
@cached_endpoint()
def analyser_swot():
    data, err = _get_json_or_error()
    if err: return err
    err = _require_fields(data, "titre", "description")
    if err: return err
    proj, err = ProjectInput.from_dict(data)
    if err: return err

    full_n = _normalize(f"{proj.titre} {proj.description} {proj.objectifs}".lower())
    sect   = proj.secteur or _secteur_from_data(data)
    kw     = extraire_mots_cles(full_n, 5)
    ms     = MARKET_SIZES.get(sect, MARKET_SIZES["general"])

    forces = [f"Expertise identifiée en {m}" for m in kw[:3]]
    if len(proj.description.split()) > 50: forces.append("Description détaillée et claire")
    if proj.objectifs:                     forces.append("Objectifs bien définis")

    faiblesses = []
    if "budget" not in full_n:     faiblesses.append("Plan financier non détaillé")
    if "equipe" not in full_n:     faiblesses.append("Composition d'équipe non précisée")
    if "concurrent" not in full_n: faiblesses.append("Analyse concurrentielle absente")
    if len(proj.description.split()) < 30: faiblesses.append("Description trop succincte")

    opportunites = [
        f"Marché {sect} en croissance ({ms['croissance']}/an)",
        "Demande croissante en solutions innovantes",
        f"TAM estimé à {ms['tam']}",
    ]
    menaces = [
        "Entrée de concurrents établis",
        "Évolution réglementaire du secteur",
        "Risque de changement technologique rapide",
    ]

    swot = {"forces": forces, "faiblesses": faiblesses, "opportunites": opportunites, "menaces": menaces}
    text  = f"=== ANALYSE SWOT : {proj.titre.upper()} ===\n\n"
    text += "FORCES :\n"       + "\n".join(f"  + {f_}" for f_ in forces)      + "\n\n"
    text += "FAIBLESSES :\n"   + "\n".join(f"  - {f_}" for f_ in faiblesses)  + "\n\n"
    text += "OPPORTUNITÉS :\n" + "\n".join(f"  > {o}" for o in opportunites)  + "\n\n"
    text += "MENACES :\n"      + "\n".join(f"  ! {m}" for m in menaces)       + "\n"

    return _ok(text, swot=swot)


# ── /api/market ───────────────────────────────────────────────────────────────
@app.route("/api/market", methods=["POST"])
@require_api_key
def analyser_marche():
    data, err = _get_json_or_error()
    if err: return err
    err = _require_fields(data, "titre")
    if err: return err
    proj, err = ProjectInput.from_dict(data)
    if err: return err

    sect = proj.secteur or _secteur_from_data(data)
    ms   = MARKET_SIZES.get(sect, MARKET_SIZES["general"])
    text = (
        f"=== ANALYSE DE MARCHÉ : {proj.titre.upper()} ===\n\n"
        f"Secteur              : {sect.capitalize()}\n"
        f"TAM (Total)          : {ms['tam']}\nSAM (Serviceable)    : {ms['sam']}\n"
        f"SOM (Obtainable)     : {ms['som']}\nCroissance annuelle  : {ms['croissance']}\n\n"
        f"--- Tendances ---\n"
        f"  - Digitalisation accélérée du secteur\n"
        f"  - Demande croissante en solutions personnalisées\n"
        f"  - Consolidation du marché (M&A en hausse)\n\n"
        f"--- Concurrence ---\n"
        f"  - 3-5 acteurs majeurs identifiés\n"
        f"  - Fenêtre d'opportunité pour les solutions innovantes\n"
        f"  - Différenciation par l'IA et l'UX recommandée\n"
    )
    return _ok(text, metadata={"secteur": sect, "market_size": ms})


# ── /api/budget ───────────────────────────────────────────────────────────────
@app.route("/api/budget", methods=["POST"])
@require_api_key
def estimer_budget():
    data, err = _get_json_or_error()
    if err: return err
    err = _require_fields(data, "titre")
    if err: return err
    proj, err = ProjectInput.from_dict(data)
    if err: return err

    sect = proj.secteur or _secteur_from_data(data)
    try: duree_m  = max(1, int(data.get("duree",  6)))
    except (TypeError, ValueError): duree_m = 6
    try: equipe_n = max(1, int(data.get("equipe", 3)))
    except (TypeError, ValueError): equipe_n = 3

    cost_mult    = COST_MULTIPLIERS.get(sect, 1.0)
    base_monthly = 3000 * equipe_n * cost_mult
    total        = base_monthly * duree_m
    roi          = _roi_deterministe(proj.titre, sect)

    repartition = {
        "salaires":       round(total * 0.50),
        "marketing":      round(total * 0.20),
        "infrastructure": round(total * 0.15),
        "divers":         round(total * 0.15),
    }

    text = (
        f"=== ESTIMATION BUDGÉTAIRE : {proj.titre.upper()} ===\n\n"
        f"Durée   : {duree_m} mois\nÉquipe  : {equipe_n} personnes\n"
        f"Secteur : {sect.capitalize()} (coeff. {cost_mult}x)\n\n"
        f"--- Répartition ---\n"
        f"  Salaires & RH        : {repartition['salaires']:>12,.0f} TND (50 %)\n"
        f"  Marketing & Comm.    : {repartition['marketing']:>12,.0f} TND (20 %)\n"
        f"  Infrastructure       : {repartition['infrastructure']:>12,.0f} TND (15 %)\n"
        f"  Divers & imprévus    : {repartition['divers']:>12,.0f} TND (15 %)\n"
        f"  {'─'*42}\n"
        f"  TOTAL ESTIMÉ         : {total:>12,.0f} TND\n\n"
        f"  Budget mensuel moy.  : {base_monthly:>12,.0f} TND/mois\n\n"
        f"--- ROI estimé ---\n"
        f"  Break-even prévu     : {duree_m + 6} mois\n  ROI à 2 ans            : ~{roi} %\n"
    )
    return _ok(text, metadata={
        "budget_total": total, "budget_mensuel": base_monthly,
        "repartition": repartition, "duree_mois": duree_m, "equipe_taille": equipe_n,
        "secteur": sect, "coefficient_sectoriel": cost_mult, "roi_estime_pct": roi,
    })


# ── /api/export_pdf ───────────────────────────────────────────────────────────
@app.route("/api/export_pdf", methods=["POST"])
@require_api_key
@limiter.limit("10 per minute")
def export_pdf():
    data, err = _get_json_or_error()
    if err: return err
    err = _require_fields(data, "titre", "contenu")
    if err: return err

    titre   = str(data["titre"]).strip()
    contenu = str(data["contenu"]).strip()
    if len(contenu) > FIELD_LIMITS["contenu"]:
        return _err(f"'contenu' dépasse {FIELD_LIMITS['contenu']} caractères.", 422)

    type_analyse = str(data.get("type_analyse", "Analyse IA")).strip()

    try:
        from reportlab.lib.enums import TA_CENTER
        from reportlab.lib.pagesizes import A4
        from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
        from reportlab.lib.units import cm
        from reportlab.platypus import Paragraph, SimpleDocTemplate, Spacer
    except ImportError:
        return _err("reportlab non installé.", 500, "Exécutez : pip install reportlab")

    try:
        buf    = io.BytesIO()
        doc    = SimpleDocTemplate(buf, pagesize=A4, topMargin=2*cm, bottomMargin=2*cm,
                                   leftMargin=2.5*cm, rightMargin=2.5*cm)
        styles = getSampleStyleSheet()
        s_title  = ParagraphStyle("T",  parent=styles["Title"],   fontSize=22, spaceAfter=12, alignment=TA_CENTER)
        s_sub    = ParagraphStyle("S",  parent=styles["Normal"],  fontSize=12, spaceAfter=20, alignment=TA_CENTER)
        s_body   = ParagraphStyle("B",  parent=styles["Normal"],  fontSize=11, leading=16,    spaceAfter=8)
        s_h2     = ParagraphStyle("H2", parent=styles["Heading2"],fontSize=14, spaceAfter=8)
        s_h3     = ParagraphStyle("H3", parent=styles["Heading3"],fontSize=12, spaceAfter=6)
        s_footer = ParagraphStyle("F",  parent=styles["Normal"],  fontSize=9,  alignment=TA_CENTER)

        elems = [
            Paragraph(f"StartHub — {_safe(type_analyse)}", s_title),
            Paragraph(f"Projet : {_safe(titre)}", s_sub),
            Paragraph(f"Date : {datetime.now().strftime('%d/%m/%Y %H:%M')}", s_sub),
            Spacer(1, 0.5*cm),
        ]
        for line in contenu.split("\n"):
            line = line.strip()
            if not line:
                elems.append(Spacer(1, 0.3*cm))
            elif line.startswith("==="):
                elems.append(Paragraph(line.replace("=", "").strip(), s_h2))
            elif line.startswith("---"):
                elems.append(Paragraph(line.replace("-", "").strip(), s_h3))
            else:
                safe_line = line.replace("&","&amp;").replace("<","&lt;").replace(">","&gt;")
                elems.append(Paragraph(safe_line, s_body))

        elems += [Spacer(1, 1*cm), Paragraph("Généré par StartHub — Analyseur de Projets IA v5.0", s_footer)]
        doc.build(elems)
        pdf_bytes = buf.getvalue()
        b64  = base64.b64encode(pdf_bytes).decode("utf-8")
        slug = re.sub(r"[^a-z0-9]+", "_", titre.lower()).strip("_")
        return _ok({"pdf_base64": b64, "filename": f"StartHub_{slug}.pdf", "size_bytes": len(pdf_bytes)})
    except Exception as e:
        logger.exception("export_pdf failed")
        return _err(str(e), 500)


# ── /api/export_csv ──────────────────────────────────────────────────────────
@app.route("/api/export_csv", methods=["POST"])
@require_api_key
def export_csv():
    """Exporte l'analyse complète sous forme de CSV (résumé des champs clés)."""
    data, err = _get_json_or_error()
    if err: return err
    err = _require_fields(data, "titre")
    if err: return err
    proj, err = ProjectInput.from_dict(data)
    if err: return err

    sect   = proj.secteur or _secteur_from_data(data)
    score_c, details_c = evaluer_completude(proj.titre, proj.description, proj.objectifs, sect)
    sent, s_val = analyser_sentiment(proj.description)
    maturite = detecter_maturite(proj.description, proj.objectifs)
    kw = extraire_mots_cles(f"{proj.titre} {proj.description}", 5)

    buf = io.StringIO()
    w = csv.writer(buf)
    w.writerow(["Champ", "Valeur"])
    rows = [
        ("Titre", proj.titre), ("Secteur", sect), ("Maturité", maturite),
        ("Score complétude", f"{score_c}/100"), ("Sentiment", sent),
        ("Score sentiment", s_val), ("Mots-clés", ", ".join(kw)),
        ("Description (extrait)", truncate_word(proj.description, 100)),
        ("Objectifs", truncate_word(proj.objectifs, 100)),
        ("Date d'analyse", datetime.now().strftime("%d/%m/%Y %H:%M")),
    ]
    for label, val in rows:
        w.writerow([label, val])
    # détails complétude
    for k, v in details_c.items():
        w.writerow([f"Complétude — {k.capitalize()}", v])

    csv_b64 = base64.b64encode(buf.getvalue().encode("utf-8-sig")).decode()
    slug = re.sub(r"[^a-z0-9]+", "_", proj.titre.lower()).strip("_")
    return _ok({"csv_base64": csv_b64, "filename": f"StartHub_{slug}.csv"})


# ── /api/pitch_deck ──────────────────────────────────────────────────────────
@app.route("/api/pitch_deck", methods=["POST"])
@require_api_key
def generer_pitch_deck():
    data, err = _get_json_or_error()
    if err: return err
    err = _require_fields(data, "titre", "description")
    if err: return err
    proj, err = ProjectInput.from_dict(data)
    if err: return err

    sect     = proj.secteur or _secteur_from_data(data)
    kw       = extraire_mots_cles(f"{proj.titre} {proj.description} {proj.objectifs}", 6)
    maturite = detecter_maturite(proj.description, proj.objectifs)
    ms       = MARKET_SIZES.get(sect, MARKET_SIZES["general"])

    slides = [
        {"numero":1,"titre":"Le Problème","sous_titre":"Pourquoi ce projet est nécessaire","style":"dark_blue",
         "points":[f"Les solutions actuelles dans le secteur {sect} sont insuffisantes",
                   "Les utilisateurs expriment un besoin croissant de solutions innovantes",
                   "Le marché manque d'outils adaptés aux exigences modernes"]},
        {"numero":2,"titre":"Notre Solution","sous_titre":proj.titre,"style":"purple",
         "points":[truncate_word(proj.description, 120),
                   f"Technologies clés : {', '.join(kw[:3]) if kw else 'IA, Cloud, UX'}",
                   f"Phase actuelle : {maturite}"]},
        {"numero":3,"titre":"Le Marché","sous_titre":f"Secteur {sect.capitalize()}","style":"teal",
         "points":[f"TAM : {ms['tam']}",f"Croissance annuelle du secteur : {ms['croissance']}",
                   "Fenêtre d'opportunité ouverte pour 18-24 mois"],
         "metrics":{"TAM":ms["tam"],"SAM":ms["sam"],"SOM":ms["som"]}},
        {"numero":4,"titre":"Modèle Économique","sous_titre":"Comment nous générons du revenu","style":"orange",
         "points":["Abonnement SaaS mensuel/annuel (modèle récurrent)",
                   "Offre freemium pour l'acquisition + plans premium",
                   "Services professionnels et consulting sectoriel"]},
        {"numero":5,"titre":"Équipe & Prochaines Étapes","sous_titre":"Notre roadmap","style":"green",
         "points":[f"Objectifs : {truncate_word(proj.objectifs, 100) if proj.objectifs else 'Lancement MVP dans 3 mois'}",
                   "Q1 : Développement MVP + bêta test","Q2 : Lancement + premiers clients",
                   "Q3-Q4 : Croissance + levée de fonds"]},
    ]
    return _ok({"slides": slides, "titre_projet": proj.titre, "secteur": sect})


# ── /api/risques ──────────────────────────────────────────────────────────────
@app.route("/api/risques", methods=["POST"])
@require_api_key
def analyser_risques():
    data, err = _get_json_or_error()
    if err: return err
    err = _require_fields(data, "titre", "description")
    if err: return err
    proj, err = ProjectInput.from_dict(data)
    if err: return err

    budget = str(data.get("budget", "")).strip()
    full_n = _normalize(f"{proj.titre} {proj.description} {proj.objectifs}".lower())
    sect   = proj.secteur or _secteur_from_data(data)
    base   = COMPLEXITE_PAR_SECTEUR.get(sect, COMPLEXITE_PAR_SECTEUR["general"])

    dims = {
        "technique":     min(1.0, base["technique"]   + (-0.1 if "prototype" in full_n else 0.0)),
        "financier":     0.8 if not budget else 0.4,
        "marche":        min(1.0, base["commerciale"] + (0.15 if "concurrent" not in full_n else -0.1)),
        "reglementaire": base["reglementaire"],
        "equipe":        0.7 if ("equipe" not in full_n and "team" not in full_n) else 0.3,
        "timing":        base["timing"],
    }
    dims = {k: round(min(1.0, v), 2) for k, v in dims.items()}
    score_num    = round(sum(dims.values()) / len(dims), 2) if dims else 0.0
    score_global = "Faible" if score_num < 0.4 else ("Moyen" if score_num < 0.65 else "Élevé")

    RISK_DETAILS = {
        "financier":     ("Le plan de financement n'est pas suffisamment détaillé.",
                          "Élaborer un business plan financier avec projections sur 3 ans."),
        "technique":     (f"La complexité technique du secteur {sect} est significative.",
                          "Prévoir une phase POC/prototype avant le développement complet."),
        "marche":        ("L'analyse concurrentielle est insuffisante.",
                          "Réaliser une étude de marché et identifier les concurrents directs."),
        "reglementaire": (f"Le secteur {sect} est fortement réglementé.",
                          "Consulter un juriste spécialisé et anticiper les certifications."),
        "equipe":        ("La composition de l'équipe n'est pas clairement définie.",
                          "Définir les rôles clés et identifier les compétences manquantes."),
        "timing":        ("Le calendrier de lancement peut être contraint.",
                          "Établir un planning réaliste avec des jalons intermédiaires."),
    }

    risques = [
        {"type": dim.capitalize(), "niveau": "élevé" if score >= 0.7 else "moyen",
         "score": score, "description": RISK_DETAILS[dim][0], "mitigation": RISK_DETAILS[dim][1]}
        for dim, score in dims.items() if score >= 0.6
    ]

    return _ok({
        "dimensions": dims, "score_global": score_global, "score_numerique": score_num,
        "risques": risques, "secteur": sect, "titre": proj.titre,
    })


# ── /api/site_web ─────────────────────────────────────────────────────────────
@app.route("/api/site_web", methods=["POST"])
@require_api_key
def generer_site_web():
    data, err = _get_json_or_error()
    if err: return err
    err = _require_fields(data, "titre")
    if err: return err
    proj, err = ProjectInput.from_dict(data)
    if err: return err

    sect   = proj.secteur or _secteur_from_data(data)
    colors = SECTOR_COLORS.get(sect, SECTOR_COLORS["general"])
    kw     = extraire_mots_cles(f"{proj.titre} {proj.description}", 6)

    feature_descs = [
        f"Exploitez la puissance de {kw[0].capitalize() if kw else 'innovation'} pour transformer votre expérience.",
        f"Intégration de {kw[1].capitalize() if len(kw) > 1 else 'solutions avancées'} pour une performance optimale.",
        f"Tirez parti de {kw[2].capitalize() if len(kw) > 2 else 'agilité'} pour accélérer votre croissance.",
    ]
    features = [
        {"icon": FEATURE_ICONS[i % len(FEATURE_ICONS)],
         "title": _safe((kw[i] if i < len(kw) else f"Fonctionnalité {i+1}").capitalize()),
         "desc":  _safe(feature_descs[i])}
        for i in range(3)
    ]

    s_titre       = _safe(proj.titre)
    s_description = _safe(proj.description)
    s_objectifs   = _safe(proj.objectifs)
    s_sect        = _safe(sect.capitalize())
    slug          = re.sub(r"[^a-z0-9]+", "-", proj.titre.lower()).strip("-")
    s_slug        = _safe(slug)
    year          = datetime.now().year
    desc_hero     = _safe(truncate_word(proj.description, 180))

    features_html = "\n".join(
        f"""        <div class="feature-card">
            <div class="feature-icon">{f['icon']}</div>
            <h3>{f['title']}</h3>
            <p>{f['desc']}</p>
        </div>"""
        for f in features
    )
    objectives_html = (
        f"""        <div class="objectives">
            <h3>🎯 Objectifs</h3>
            <p>{s_objectifs}</p>
        </div>"""
        if proj.objectifs else ""
    )

    html = f"""<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{s_titre}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{{--primary:{colors['primary']};--gradient:{colors['gradient']};}}
        *{{margin:0;padding:0;box-sizing:border-box;}}
        body{{font-family:'Inter',sans-serif;color:#1a1a2e;line-height:1.7;}}
        nav{{position:sticky;top:0;background:rgba(255,255,255,0.95);backdrop-filter:blur(12px);
             padding:16px 40px;display:flex;align-items:center;justify-content:space-between;
             box-shadow:0 1px 3px rgba(0,0,0,0.06);z-index:100;}}
        .logo{{font-weight:800;font-size:1.2rem;color:var(--primary);}}
        .links a{{text-decoration:none;color:#64748b;font-size:.95rem;margin-left:24px;}}
        .links a:hover{{color:var(--primary);}}
        .hero{{background:var(--gradient);color:white;padding:120px 20px 100px;text-align:center;}}
        .hero h1{{font-size:clamp(2rem,5vw,3.5rem);font-weight:800;margin-bottom:20px;}}
        .hero p{{font-size:1.15rem;opacity:.9;max-width:580px;margin:0 auto 36px;}}
        .badge{{display:inline-block;background:rgba(255,255,255,.2);padding:6px 18px;
                border-radius:50px;font-size:.85rem;font-weight:600;margin-bottom:20px;
                border:1px solid rgba(255,255,255,.3);}}
        .btn{{display:inline-block;padding:15px 40px;background:white;color:var(--primary);
              font-weight:700;border-radius:50px;text-decoration:none;margin:0 8px;
              transition:transform .2s,box-shadow .2s;}}
        .btn:hover{{transform:translateY(-3px);box-shadow:0 12px 35px rgba(0,0,0,.2);}}
        .btn.outline{{background:transparent;color:white;border:2px solid rgba(255,255,255,.7);}}
        section{{padding:80px 20px;}}
        .container{{max-width:1000px;margin:0 auto;}}
        .section-title{{text-align:center;font-size:clamp(1.5rem,3vw,2.2rem);font-weight:800;margin-bottom:52px;}}
        .features{{background:#f8fafc;}}
        .features-grid{{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;}}
        .feature-card{{background:#fff;border-radius:16px;padding:36px;border:1px solid #e2e8f0;
                        transition:transform .2s,box-shadow .2s;}}
        .feature-card:hover{{transform:translateY(-5px);box-shadow:0 16px 50px rgba(0,0,0,.08);}}
        .feature-icon{{font-size:2.8rem;margin-bottom:18px;}}
        .feature-card h3{{font-size:1.15rem;font-weight:700;margin-bottom:10px;}}
        .feature-card p{{color:#64748b;font-size:.95rem;}}
        .about-grid{{display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:center;}}
        .objectives{{background:#f8fafc;border-left:4px solid var(--primary);
                     padding:24px 28px;border-radius:0 16px 16px 0;margin-top:24px;}}
        .objectives h3{{font-weight:700;margin-bottom:10px;color:var(--primary);}}
        .about-visual{{background:var(--gradient);border-radius:16px;padding:40px;
                        color:white;text-align:center;}}
        .stat{{margin-bottom:24px;}}.stat-num{{font-size:2.5rem;font-weight:800;}}
        .stat-label{{font-size:.9rem;opacity:.8;}}
        .cta{{background:var(--gradient);color:white;text-align:center;}}
        .cta h2{{font-size:clamp(1.5rem,3vw,2.2rem);font-weight:800;margin-bottom:16px;}}
        .cta p{{opacity:.9;margin-bottom:36px;}}
        footer{{text-align:center;padding:32px 20px;color:#64748b;font-size:.85rem;
                border-top:1px solid #e2e8f0;}}
        @media(max-width:768px){{.links,.about-visual{{display:none;}}.about-grid{{grid-template-columns:1fr;}}}}
    </style>
</head>
<body>
    <nav>
        <span class="logo">{s_titre}</span>
        <div class="links">
            <a href="#features">Fonctionnalités</a>
            <a href="#about">À propos</a>
            <a href="#contact">Contact</a>
        </div>
    </nav>
    <section class="hero">
        <div class="badge">🚀 {s_sect}</div>
        <h1>{s_titre}</h1>
        <p>{desc_hero}</p>
        <a href="#features" class="btn">Découvrir</a>
        <a href="#contact" class="btn outline">Nous contacter</a>
    </section>
    <section class="features" id="features">
        <div class="container">
            <h2 class="section-title">Nos Points Forts</h2>
            <div class="features-grid">
{features_html}
            </div>
        </div>
    </section>
    <section id="about">
        <div class="container">
            <h2 class="section-title">Notre vision</h2>
            <div class="about-grid">
                <div>
                    <p>{s_description}</p>
{objectives_html}
                </div>
                <div class="about-visual">
                    <div class="stat"><div class="stat-num">100%</div><div class="stat-label">Orienté utilisateur</div></div>
                    <div class="stat"><div class="stat-num">🏆</div><div class="stat-label">Solution innovante</div></div>
                    <div class="stat"><div class="stat-num">🌍</div><div class="stat-label">Vision globale</div></div>
                </div>
            </div>
        </div>
    </section>
    <section class="cta" id="contact">
        <div class="container">
            <h2>Rejoignez l'aventure {s_titre}</h2>
            <p>Soyez parmi les premiers à découvrir notre solution innovante.</p>
            <a href="mailto:contact@{s_slug}.com" class="btn">📬 Nous contacter</a>
        </div>
    </section>
    <footer>&copy; {year} {s_titre} · Secteur {s_sect} · Généré par <strong>StartHub IA v5.0</strong></footer>
</body>
</html>"""
    return _ok({"html": html, "filename": f"{slug}.html", "secteur": sect})


# ── /api/roadmap ──────────────────────────────────────────────────────────────
@app.route("/api/roadmap", methods=["POST"])
@require_api_key
def generer_roadmap():
    data, err = _get_json_or_error()
    if err: return err
    err = _require_fields(data, "titre")
    if err: return err
    proj, err = ProjectInput.from_dict(data)
    if err: return err

    maturite = detecter_maturite(proj.description, proj.objectifs)
    sect     = proj.secteur or _secteur_from_data(data)
    phases   = ROADMAP_TEMPLATES.get(maturite, ROADMAP_TEMPLATES["Idée préliminaire"])

    text = f"=== ROADMAP 12 MOIS : {proj.titre.upper()} ===\n"
    text += f"Maturité : {maturite} | Secteur : {sect.capitalize()}\n\n"
    for p in phases:
        text += f"{'─'*50}\n{p['phase']} — {p['titre']}\n"
        text += "Objectifs : " + " · ".join(p["objectifs"]) + "\n"
        text += "Livrables : " + " · ".join(p["livrables"]) + "\n"
        text += f"Budget    : {p['budget_pct']} % du budget total\n\n"

    return _ok(text, phases=phases, metadata={"secteur": sect, "maturite": maturite})


# ── /api/persona ──────────────────────────────────────────────────────────────
@app.route("/api/persona", methods=["POST"])
@require_api_key
def generer_persona():
    data, err = _get_json_or_error()
    if err: return err
    err = _require_fields(data, "titre")
    if err: return err
    proj, err = ProjectInput.from_dict(data)
    if err: return err

    sect     = proj.secteur or _secteur_from_data(data)
    personas = PERSONAS_PAR_SECTEUR.get(sect, PERSONAS_PAR_SECTEUR["general"])

    text = f"=== PERSONAS UTILISATEURS : {proj.titre.upper()} ===\nSecteur : {sect.capitalize()}\n\n"
    for i, p in enumerate(personas, 1):
        text += f"── PERSONA {i} : {p['nom']} ──\n"
        text += f"  Âge/Rôle        : {p['age']} · {p['role']}\n"
        text += f"  Motivations     : {', '.join(p['motivations'])}\n"
        text += f"  Frustrations    : {', '.join(p['frustrations'])}\n"
        text += f"  Canaux          : {', '.join(p['canaux'])}\n"
        text += f"  Citation clé    : « {p['citation']} »\n\n"

    return _ok(text, personas=personas, metadata={"secteur": sect, "nb_personas": len(personas)})


# ── /api/kpi ──────────────────────────────────────────────────────────────────
@app.route("/api/kpi", methods=["POST"])
@require_api_key
def generer_kpi():
    data, err = _get_json_or_error()
    if err: return err
    err = _require_fields(data, "titre")
    if err: return err
    proj, err = ProjectInput.from_dict(data)
    if err: return err

    sect = proj.secteur or _secteur_from_data(data)
    kpis = KPI_PAR_SECTEUR.get(sect, KPI_PAR_SECTEUR["general"])

    text = f"=== KPIs RECOMMANDÉS : {proj.titre.upper()} ===\nSecteur : {sect.capitalize()}\n\n"
    for i, k in enumerate(kpis, 1):
        text += f"{i}. {k['kpi']}\n   Cible      : {k['cible']}\n   Fréquence  : {k['frequence']}\n\n"

    return _ok(text, kpis=kpis, metadata={"secteur": sect, "nb_kpis": len(kpis)})


# ── /api/batch ────────────────────────────────────────────────────────────────
@app.route("/api/batch", methods=["POST"])
@require_api_key
@limiter.limit("20 per minute")
def batch_analyse():
    """
    Lance plusieurs analyses en parallèle.
    Body: {"analyses": ["resumer", "swot", "risques", ...], "titre": ..., "description": ...}
    """
    data, err = _get_json_or_error()
    if err: return err
    err = _require_fields(data, "titre", "analyses")
    if err: return err

    analyses = data.get("analyses", [])
    if not isinstance(analyses, list) or len(analyses) == 0:
        return _err("'analyses' doit être une liste non vide.", 422)
    if len(analyses) > 8:
        return _err("Maximum 8 analyses par requête batch.", 422)

    ENDPOINT_MAP = {
        "resumer": resumer, "ameliorations": ameliorations,
        "analyser": analyser, "pitch": generer_pitch,
        "viabilite": analyser_viabilite, "swot": analyser_swot,
        "market": analyser_marche, "budget": estimer_budget,
        "risques": analyser_risques, "roadmap": generer_roadmap,
        "persona": generer_persona, "kpi": generer_kpi,
    }

    results = {}
    with app.test_request_context(
            "/api/batch", method="POST",
            json=data, content_type="application/json"
    ):
        futures = {}
        for name in analyses:
            fn = ENDPOINT_MAP.get(name)
            if fn is None:
                results[name] = {"success": False, "error": f"Endpoint '{name}' inconnu"}
                continue
            futures[_executor.submit(fn)] = name

        for future in as_completed(futures, timeout=60):
            name = futures[future]
            try:
                resp = future.result()
                if hasattr(resp, "get_json"):
                    results[name] = resp.get_json()
                else:
                    results[name] = {"success": True, "result": str(resp)}
            except Exception as e:
                results[name] = {"success": False, "error": str(e)}

    return _ok(results)


# ── /api/webhook/register ─────────────────────────────────────────────────────
@app.route("/api/webhook/register", methods=["POST"])
@require_api_key
def register_webhook():
    """
    Enregistre une URL webhook pour un événement donné.
    Body: {"event": "analyse_complete", "url": "https://..."}
    """
    data, err = _get_json_or_error()
    if err: return err
    event = str(data.get("event", "")).strip()
    url   = str(data.get("url", "")).strip()
    if not event or not url:
        return _err("'event' et 'url' sont requis.", 422)
    if not url.startswith(("http://", "https://")):
        return _err("URL invalide — doit commencer par http:// ou https://", 422)
    with _webhooks_lock:
        _webhooks.setdefault(event, [])
        if url not in _webhooks[event]:
            _webhooks[event].append(url)
    return _ok(f"Webhook enregistré pour l'événement '{event}'",
               registered={"event": event, "url": url, "total": len(_webhooks[event])})


# ─────────────────────────────────────────────────────────────────────────────
# GESTION D'ERREURS
# ─────────────────────────────────────────────────────────────────────────────
@app.errorhandler(404)
def not_found(_): return _err("Endpoint introuvable.", 404, "Consultez GET /api/health.")

@app.errorhandler(405)
def method_not_allowed(_): return _err("Méthode HTTP non autorisée.", 405, "Utilisez POST.")

@app.errorhandler(413)
def payload_too_large(_): return _err("Payload trop volumineux (max 1 MB).", 413)

@app.errorhandler(429)
def too_many_requests(_): return _err("Trop de requêtes. Attendez avant de réessayer.", 429)

@app.errorhandler(500)
def internal_error(e):
    logger.exception("Unhandled 500: %s", e)
    return _err("Erreur interne du serveur.", 500, "Consultez les logs.")


# ─────────────────────────────────────────────────────────────────────────────
# MAIN
# ─────────────────────────────────────────────────────────────────────────────
if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    
    print("\n" + "=" * 65)
    print("  🚀 StartHub AI Project API v5.0")
    print(f"  🌐 http://localhost:{port}")
    print("=" * 65)
    print(f"  🔐 Auth API Key    : {'✅ ACTIVÉE' if API_KEY else '❌ désactivée'}")
    print(f"  🤖 LLM Backend     : {'✅ ACTIVÉ (' + _LLM_MODEL + ')' if _LLM_AVAILABLE else '⚠️ désactivé (mode heuristique)'}")
    print(f"  ⚡ Rate Limiting   : ✅ activé")
    print(f"  🗜️ Compression     : ✅ activée (gzip)")
    print(f"  💾 Cache TTL       : {os.environ.get('CACHE_TTL_SECONDS', '3600')}s")
    print(f"  🧵 Worker threads  : {os.environ.get('WORKER_THREADS', '4')}")
    print(f"  📊 Prometheus      : {'✅ activé' if _PROMETHEUS else '❌ désactivé'}")
    print(f"  ✅ JSON Schema     : {'✅ activé' if _JSONSCHEMA else '❌ désactivé'}")
    print("=" * 65)
    print("\n  📋 NOUVEAUX ENDPOINTS v5.0 :")
    print("    POST /api/batch            Analyses parallèles (max 8)")
    print("    POST /api/export_csv       Export CSV de l'analyse")
    print("    POST /api/webhook/register Enregistrement de webhooks")
    print("    SSE  /api/stream/pitch     Pitch en streaming temps réel")
    print("    SSE  /api/stream/analyse   Analyse en streaming temps réel")
    print("    GET  /metrics              Métriques Prometheus")
    print("=" * 65)
    print("\n  💡 EXEMPLES DE REQUÊTES :")
    print("    curl -X POST http://localhost:5000/api/health")
    print('    curl -X POST http://localhost:5000/api/analyser \\')
    print('      -H "Content-Type: application/json" \\')
    print('      -d \'{"titre":"Mon Projet","description":"Description..."}\'')
    print("=" * 65 + "\n")
    
    app.run(debug=DEBUG, port=port, threaded=True)