# Événements Symfony 6.4

Conversion du module **Événements** du projet PIDEV JavaFX vers une application web **Symfony 6.4**.

## Structure du projet

```
EvenementsSymfony6/
├── src/
│   ├── Entity/
│   │   ├── Evenement.php          ← OneToMany → Reservation
│   │   ├── Reservation.php        ← ManyToOne → Evenement, Utilisateur
│   │   └── Utilisateur.php        ← OneToMany → Reservation
│   ├── Repository/
│   │   ├── EvenementRepository.php
│   │   ├── ReservationRepository.php
│   │   └── UtilisateurRepository.php
│   ├── Controller/
│   │   ├── EvenementController.php    ← Admin CRUD /admin/evenement
│   │   ├── ReservationController.php  ← Admin CRUD /admin/reservation
│   │   └── UserEvenementController.php ← Vue utilisateur /evenements
│   └── Form/
│       ├── EvenementType.php
│       └── ReservationType.php
├── templates/
│   ├── base.html.twig             ← Sidebar violet + style global
│   ├── evenement/                 ← Vues admin événements
│   ├── reservation/               ← Vues admin réservations
│   └── user/                      ← Vues utilisateur (browse + mes réservations)
├── schema.sql                     ← Schéma + données de test
├── .env                           ← À configurer (DATABASE_URL)
└── composer.json
```

## Installation

### 1. Configurer la base de données

Modifier `.env` :
```
DATABASE_URL="mysql://root:@127.0.0.1:3306/pidev_evenements?serverVersion=8.0"
```

### 2. Créer la base + importer les données

```bash
# Option A : importer le SQL directement
mysql -u root < schema.sql

# Option B : utiliser Doctrine
php bin/console doctrine:database:create
php bin/console doctrine:schema:create
```

### 3. Installer les dépendances

```bash
composer install
```

### 4. Lancer le serveur

```bash
symfony server:start
# ou
php -S localhost:8000 -t public/
```

### 5. Ouvrir dans le navigateur

| URL | Description |
|-----|-------------|
| `http://localhost:8000/evenements` | Vue utilisateur — parcourir les événements |
| `http://localhost:8000/evenements/mes-reservations` | Mes réservations |
| `http://localhost:8000/admin/evenement` | Admin — gestion des événements |
| `http://localhost:8000/admin/reservation` | Admin — gestion des réservations |

## Fonctionnalités

### Vue Utilisateur
- Parcourir tous les événements en cards (style identique au JavaFX)
- Filtrer par prix (min/max), trier par date / prix / titre
- Recherche par titre / lieu / description
- Réserver un événement (décrémente la capacité automatiquement)
- Annuler une réservation (restaure la capacité)
- Voir ses réservations personnelles

### Vue Admin
- CRUD complet des événements (créer, voir, modifier, supprimer)
- CRUD complet des réservations
- Dashboard avec statistiques (total, à venir, réservations, revenu)
- Gestion de la capacité lors des créations/suppressions/modifications

## Style

Reproduction fidèle du thème JavaFX :
- Fond : `#fdf4fb` (rose pâle)
- Gradient header : `#A155B9 → #c472d4`
- Sidebar : `#A155B9 → #8a4ca0`
- Accent rose : `#F765A3`
- Cartes blanches avec bordures `#ead5f0`
