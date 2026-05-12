Starthub 🚀
Plateforme de Gestion de Startups & Innovation

Starthub est une plateforme web développée avec Symfony 6.4 permettant de centraliser la gestion des startups, des projets innovants, des équipes, des ressources, des événements et du recrutement.

📌 Fonctionnalités Principales
👤 Gestion des Utilisateurs
Inscription et authentification
Gestion des rôles
Modification du profil
Réinitialisation du mot de passe
Réclamations et support
📂 Gestion des Projets
Soumission d’idées de projets
Validation par l’administrateur
Gestion des tâches
Gestion des équipes
Suivi d’avancement
🛠 Gestion des Ressources
Ajout de ressources
Location ou achat de ressources
Gestion de disponibilité
Filtrage des ressources
📅 Gestion des Événements
Création d’événements
Réservation de places
Gestion des capacités
💼 Gestion RH
Publication d’offres d’emploi
Dépôt de candidatures
Upload de CV
Sélection des candidats
👥 Acteurs du Système
Administrateur
Entrepreneur
Membre d’équipe
Gestionnaire de ressources
Candidat
🧰 Technologies Utilisées
Backend
PHP 8.2
Symfony 6.4
Frontend
Twig
HTML5
CSS3
Bootstrap 5
JavaScript
Base de données
MySQL
Outils
Doctrine ORM
Composer
Git & GitHub
Symfony CLI
🔐 Sécurité
Authentification sécurisée
Hashage des mots de passe
Gestion des rôles et permissions
Protection CSRF
Validation des formulaires
📁 Modules du Projet
User Management
Gestion des Projets
Gestion des Tâches
Gestion des Ressources
Gestion des Événements
Gestion RH
Réclamations
⚙️ Installation
1️⃣ Cloner le projet
git clone https://github.com/votre-username/starthub.git
2️⃣ Accéder au dossier
cd starthub
3️⃣ Installer les dépendances
composer install
4️⃣ Configurer le fichier .env

Modifier les informations de connexion MySQL :

DATABASE_URL="mysql://root:password@127.0.0.1:3306/starthub"
5️⃣ Créer la base de données
php bin/console doctrine:database:create
6️⃣ Exécuter les migrations
php bin/console doctrine:migrations:migrate
7️⃣ Lancer le serveur Symfony
symfony server:start
📊 États des Projets
En attente
Accepté
Refusé
En cours
Terminé
👨‍💻 Équipe

Projet académique réalisé dans le cadre du développement d’une plateforme de gestion de startups et d’innovation.

📄 Licence

Ce projet est destiné à un usage éducatif et académique.
