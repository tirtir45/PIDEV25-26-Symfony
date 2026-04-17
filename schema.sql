-- ============================================================
-- Schéma SQL — Gestion des Événements (Symfony 6.4)
-- Compatible avec le projet PIDEV Java (même structure utilisateurs)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `starthub2.0` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `starthub2.0`;

-- ─────────────────────────────────────────────────────────────
-- 1. Utilisateurs  (same as Java project)
--    id_role: 1 = Admin, 2 = Entrepreneur
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS utilisateurs (
    id_utilisateur  INT AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(255)  DEFAULT NULL,
    email           VARCHAR(255)  NOT NULL UNIQUE,
    mot_de_passe    VARCHAR(255)  NOT NULL,         -- BCrypt hash
    photo           VARCHAR(255)  DEFAULT NULL,
    bio             TEXT          DEFAULT NULL,
    competences     VARCHAR(255)  DEFAULT NULL,
    telephone       VARCHAR(50)   DEFAULT NULL,
    id_role         INT           NOT NULL DEFAULT 2, -- 1=Admin, 2=Entrepreneur
    date_inscription DATETIME     DEFAULT NULL,
    actif           TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- 2. Événements
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS evenements (
    id_evenement    INT AUTO_INCREMENT PRIMARY KEY,
    titre           VARCHAR(255)  NOT NULL,
    date_evenement  DATE          NOT NULL,
    lieu            VARCHAR(255)  NOT NULL,
    capacite        INT           NOT NULL DEFAULT 0,
    description     TEXT          DEFAULT NULL,
    prix            DOUBLE        NOT NULL DEFAULT 0.0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- 3. Réservations  (ManyToOne → Evenement, ManyToOne → Utilisateur)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS reservations (
    id_reservation  INT AUTO_INCREMENT PRIMARY KEY,
    id_evenement    INT      NOT NULL,
    id_utilisateur  INT      NOT NULL,
    date_reservation DATETIME NOT NULL,
    CONSTRAINT fk_res_evenement
        FOREIGN KEY (id_evenement)   REFERENCES evenements(id_evenement)     ON DELETE CASCADE,
    CONSTRAINT fk_res_utilisateur
        FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs(id_utilisateur) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- Données de test
-- ─────────────────────────────────────────────────────────────

-- Admin account  (password: admin123)
-- BCrypt hash of "admin123" with cost 12:
INSERT INTO utilisateurs (nom, email, mot_de_passe, id_role, date_inscription, actif) VALUES
('Administrateur', 'admin@starthub.tn',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 1, NOW(), 1);

-- Entrepreneur accounts (password: test123)
INSERT INTO utilisateurs (nom, email, mot_de_passe, id_role, date_inscription, actif) VALUES
('Alice Dupont',  'alice@esprit.tn',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 2, NOW(), 1),
('Bob Martin',    'bob@esprit.tn',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 2, NOW(), 1),
('Clara Benali',  'clara@esprit.tn',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 2, NOW(), 1);

-- Events
INSERT INTO evenements (titre, date_evenement, lieu, capacite, description, prix) VALUES
('Hackathon StartHub 2026',            '2026-05-15', 'ESPRIT School, Tunis',      100, 'Un hackathon de 48h pour les entrepreneurs du numérique.', 0.00),
('Workshop Intelligence Artificielle', '2026-06-10', 'Centre de congrès, Lac 2',   60, 'Formation intensive sur le Machine Learning et les LLMs.', 49.00),
('Networking Entrepreneurs',           '2026-05-28', 'Hotel Barceló, La Marsa',   200, 'Soirée networking pour entrepreneurs, investisseurs et mentors.', 25.00),
('Pitch Day PIDEV 2026',               '2026-07-01', 'ESPRIT — Amphithéâtre A',   150, 'Présentation finale des projets PIDEV 2025-2026.', 0.00),
('Conférence Digital Marketing',       '2026-06-20', 'Mövenpick Hotel, Tunis',     80, 'Les dernières tendances du marketing digital.', 35.00),
('Bootcamp Symfony 6.4',               '2026-05-10', 'En ligne (Zoom)',             50, 'Trois jours pour maîtriser Symfony 6.4.', 15.00);

-- Sample reservations
INSERT INTO reservations (id_evenement, id_utilisateur, date_reservation) VALUES
(1, 2, NOW()), (1, 3, NOW()), (2, 2, NOW()), (3, 4, NOW()), (6, 4, NOW());

-- Adjust capacities
UPDATE evenements e
SET capacite = capacite - (SELECT COUNT(*) FROM reservations r WHERE r.id_evenement = e.id_evenement);

-- ─────────────────────────────────────────────────────────────
-- NOTE: All test accounts use the same password: "password"
-- The hash above is the standard BCrypt hash for "password"
-- (cost 12, compatible with Java's PasswordUtil.hashPassword)
--
-- Test credentials:
--   admin@starthub.tn  / password  → ROLE_ADMIN
--   alice@esprit.tn    / password  → ROLE_USER (Entrepreneur)
--   bob@esprit.tn      / password  → ROLE_USER
--   clara@esprit.tn    / password  → ROLE_USER
-- ─────────────────────────────────────────────────────────────
