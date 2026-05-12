# 🚀 StartHub

> **Plateforme centralisée de gestion de startups & innovation**  
> Développée avec **Symfony 6.4** | Projet PIDEV 2025–2026

---

## 📋 Table des matières

- [Présentation](#-présentation)
- [Fonctionnalités](#-fonctionnalités)
- [Acteurs du système](#-acteurs-du-système)
- [Prérequis](#-prérequis)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Lancer le projet](#-lancer-le-projet)
- [Structure du projet](#-structure-du-projet)
- [Technologies utilisées](#-technologies-utilisées)
- [Équipe](#-équipe)

---

## 🌟 Présentation

**StartHub** est une plateforme digitale tout-en-un conçue pour accompagner les entrepreneurs dans la création et le développement de leurs startups. Elle centralise la gestion des projets, des équipes, des ressources, des événements et du recrutement en un seul endroit.

---

## ✨ Fonctionnalités

### 👤 Gestion des utilisateurs
- Inscription, connexion et déconnexion sécurisées
- Réinitialisation du mot de passe
- Gestion de profil (photo, bio, compétences, coordonnées)
- Système de réclamations / support

### 📁 Gestion des projets
- Soumission d'idées de projets (titre, description, secteur, objectifs)
- Validation ou refus par l'administrateur (avec commentaire)
- Création de to-do lists et assignation de tâches
- Suivi de l'avancement par statut : `En attente` → `Accepté / Refusé` → `En cours` → `Terminé`

### 🔧 Gestion des ressources
- Publication de ressources (matériel, espace, équipements, services)
- Définition du type (location / achat), prix et disponibilité
- Demande de location ou d'achat par les entrepreneurs
- Acceptation / refus des demandes par les fournisseurs

### 📅 Gestion des événements
- Création d'événements (workshops, hackathons, formations) par l'admin
- Réservation et annulation de places par les entrepreneurs
- Consultation du calendrier des événements

### 👥 Gestion RH & Recrutement
- Publication d'offres d'emploi par les entrepreneurs
- Dépôt de CV et candidature par les candidats
- Filtrage des candidatures par compétences / expérience
- Sélection des candidats

---

## 🎭 Acteurs du système

| Rôle | Responsabilités principales |
|------|----------------------------|
| **Administrateur** | Supervise la plateforme, valide les projets, gère les événements et les utilisateurs |
| **Entrepreneur** | Soumet des projets, gère son équipe, accède aux ressources et recrute |
| **Membre d'équipe** | Exécute les tâches assignées, met à jour leur statut |
| **Gestionnaire de ressources** | Publie et gère des ressources disponibles à la location ou à l'achat |
| **Candidat** | Consulte les offres d'emploi et soumet ses candidatures |

---

## ✅ Prérequis

Assurez-vous d'avoir installé les outils suivants :

- **PHP** >= 8.2
- **Composer** >= 2.x
- **Symfony CLI** >= 5.x
- **MySQL** >= 8.0 (ou MariaDB)
- **Node.js** >= 18.x & **npm** (pour les assets)
- **Git**

---

## 🛠️ Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/tirtir45/PIDEV25-26-Symfony.git
cd PIDEV25-26-Symfony
```

### 2. Installer les dépendances PHP

```bash
composer install
```

### 3. Installer les dépendances JavaScript

```bash
npm install
npm run build
```

---

## ⚙️ Configuration

### 1. Créer le fichier d'environnement

```bash
cp .env .env.local
```

### 2. Configurer la base de données dans `.env.local`

```dotenv
DATABASE_URL="mysql://user:password@127.0.0.1:3306/starthub?serverVersion=8.0&charset=utf8mb4"
```

Remplacez `user` et `password` par vos identifiants MySQL.

### 3. Créer la base de données et exécuter les migrations

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### 4. (Optionnel) Charger les données de test

```bash
php bin/console doctrine:fixtures:load
```

---

## ▶️ Lancer le projet

```bash
symfony server:start
```

Accédez à l'application sur : [http://localhost:8000](http://localhost:8000)

> Ou avec le serveur PHP intégré :
> ```bash
> php -S localhost:8000 -t public/
> ```

---

## 📂 Structure du projet

```
PIDEV25-26-Symfony/
├── assets/               # Fichiers JS / CSS front-end
├── config/               # Configuration Symfony (routes, services, packages)
├── migrations/           # Migrations Doctrine
├── public/               # Point d'entrée public (index.php)
├── src/
│   ├── Controller/       # Contrôleurs Symfony
│   ├── Entity/           # Entités Doctrine (modèles)
│   ├── Form/             # Formulaires Symfony
│   ├── Repository/       # Repositories Doctrine
│   └── Security/         # Authentification & autorisation
├── templates/            # Templates Twig
├── tests/                # Tests unitaires et fonctionnels
├── translations/         # Fichiers de traduction
├── .env                  # Variables d'environnement (template)
├── composer.json
└── symfony.lock
```

---

## 🧰 Technologies utilisées

| Technologie | Version | Usage |
|-------------|---------|-------|
| **Symfony** | 6.4 LTS | Framework PHP principal |
| **Doctrine ORM** | 3.x | Gestion de la base de données |
| **Twig** | 3.x | Moteur de templates |
| **MySQL** | 8.0 | Base de données relationnelle |
| **Webpack Encore** | — | Bundling des assets |
| **Bootstrap** | 5.x | UI / design responsive |
| **PHP** | 8.2+ | Langage back-end |

---

## 🔐 Sécurité

- Mots de passe chiffrés avec **bcrypt**
- Contrôle d'accès basé sur les **rôles** (ROLE_ADMIN, ROLE_ENTREPRENEUR, etc.)
- Protection des données personnelles conforme aux bonnes pratiques
- Tokens CSRF sur les formulaires sensibles

---

## 👨‍💻 Équipe

Projet réalisé dans le cadre du **PIDEV 2025–2026**.

> Envie de contribuer ? Créez une branche, faites vos modifications et ouvrez une **Pull Request** !

---

## 📄 Licence

Ce projet est développé à des fins académiques dans le cadre du cursus PIDEV.

---

<div align="center">
  <sub>Made with ❤️ — StartHub © 2025–2026</sub>
</div>
