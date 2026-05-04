-- ============================================================
-- StartHub Database Migration Script
-- Apply to: starthub database
-- Run this SQL in phpMyAdmin or MySQL CLI
-- ============================================================

-- 1. TABLE: taches
--    Add missing columns: priorite, date_fin_reelle
-- ------------------------------------------------------------
ALTER TABLE `taches`
    ADD COLUMN IF NOT EXISTS `priorite` VARCHAR(20) NULL DEFAULT NULL AFTER `statut`,
    ADD COLUMN IF NOT EXISTS `date_fin_reelle` DATETIME NULL DEFAULT NULL AFTER `date_limite`;

-- 2. TABLE: sprints
--    Make nullable and set defaults to match updated entity
-- ------------------------------------------------------------
ALTER TABLE `sprints`
    MODIFY COLUMN `nom` VARCHAR(100) NULL DEFAULT NULL,
    MODIFY COLUMN `objectif` TEXT NULL DEFAULT NULL,
    MODIFY COLUMN `date_debut` DATE NULL DEFAULT NULL,
    MODIFY COLUMN `date_fin` DATE NULL DEFAULT NULL,
    MODIFY COLUMN `statut` VARCHAR(50) NOT NULL DEFAULT 'planifie',
    MODIFY COLUMN `capacite_equipe` INT NOT NULL DEFAULT 0,
    MODIFY COLUMN `velocite_prevue` INT NOT NULL DEFAULT 0,
    MODIFY COLUMN `velocite_reelle` INT NOT NULL DEFAULT 0;

-- 3. TABLE: userstory
--    Ensure id_sprint FK is nullable
-- ------------------------------------------------------------
ALTER TABLE `userstory`
    MODIFY COLUMN `id_sprint` INT NULL DEFAULT NULL;

-- Add FK constraint only if it doesn't exist
-- (skip if already present)
-- ALTER TABLE `userstory`
--     ADD CONSTRAINT `fk_userstory_sprint`
--     FOREIGN KEY (`id_sprint`) REFERENCES `sprints`(`id_sprint`) ON DELETE SET NULL;

-- 4. TABLE: criteres_evaluation
--    No AUTO_INCREMENT on PK (id_critere is plain PK)
-- ------------------------------------------------------------
-- Nothing to change if table already exists correctly.
-- Create if not exists:
CREATE TABLE IF NOT EXISTS `criteres_evaluation` (
    `id_critere` INT NOT NULL,
    `nom` VARCHAR(100) NOT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `poids` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    PRIMARY KEY (`id_critere`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. TABLE: evaluations_projet
--    Create if not exists with proper FKs
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `evaluations_projet` (
    `id_evaluation` INT NOT NULL AUTO_INCREMENT,
    `id_projet` INT NOT NULL,
    `id_evaluateur` INT NULL DEFAULT NULL,
    `note_globale` DECIMAL(5,2) NULL DEFAULT NULL,
    `commentaire` TEXT NULL DEFAULT NULL,
    `date_evaluation` DATETIME NULL DEFAULT NULL,
    `evaluation_automatique` TINYINT(1) NOT NULL DEFAULT 0,
    `details_json` LONGTEXT NULL DEFAULT NULL COMMENT '(DC2Type:json)',
    PRIMARY KEY (`id_evaluation`),
    KEY `IDX_EVAL_PROJET` (`id_projet`),
    KEY `IDX_EVAL_USER` (`id_evaluateur`),
    CONSTRAINT `fk_eval_projet` FOREIGN KEY (`id_projet`) REFERENCES `projets` (`id_projet`) ON DELETE CASCADE,
    CONSTRAINT `fk_eval_user` FOREIGN KEY (`id_evaluateur`) REFERENCES `utilisateurs` (`id_utilisateur`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. TABLE: ressources_projet (new)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ressources_projet` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `id_projet` INT NOT NULL,
    `nom` VARCHAR(100) NOT NULL,
    `description` LONGTEXT NULL DEFAULT NULL,
    `type` VARCHAR(50) NOT NULL,
    `emplacement` VARCHAR(100) NULL DEFAULT NULL,
    `statut` VARCHAR(20) NOT NULL DEFAULT 'disponible',
    `caracteristiques` LONGTEXT NULL DEFAULT NULL COMMENT '(DC2Type:json)',
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `IDX_RESSOURCE_PROJET` (`id_projet`),
    CONSTRAINT `fk_ressource_projet` FOREIGN KEY (`id_projet`) REFERENCES `projets` (`id_projet`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. TABLE: fichiers_projet (new)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fichiers_projet` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `id_projet` INT NOT NULL,
    `id_uploaded_by` INT NOT NULL,
    `nom_original` VARCHAR(255) NOT NULL,
    `nom_fichier` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `taille` INT NOT NULL,
    `extension` VARCHAR(10) NOT NULL,
    `uploaded_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `IDX_FICHIER_PROJET` (`id_projet`),
    KEY `IDX_FICHIER_USER` (`id_uploaded_by`),
    CONSTRAINT `fk_fichier_projet` FOREIGN KEY (`id_projet`) REFERENCES `projets` (`id_projet`) ON DELETE CASCADE,
    CONSTRAINT `fk_fichier_user` FOREIGN KEY (`id_uploaded_by`) REFERENCES `utilisateurs` (`id_utilisateur`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Add AI & market analysis columns to projets (if missing)
-- ------------------------------------------------------------
ALTER TABLE `projets`
    ADD COLUMN IF NOT EXISTS `ai_score_global` DECIMAL(5,2) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `ai_analysis_data` LONGTEXT NULL DEFAULT NULL COMMENT '(DC2Type:json)',
    ADD COLUMN IF NOT EXISTS `ai_analyzed_at` DATETIME NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `market_score` DECIMAL(5,2) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `market_trend` VARCHAR(20) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `market_data` LONGTEXT NULL DEFAULT NULL COMMENT '(DC2Type:json)',
    ADD COLUMN IF NOT EXISTS `market_analyzed_at` DATETIME NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `patent_novelty_score` DECIMAL(5,2) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `patent_total_count` INT NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `patent_data` LONGTEXT NULL DEFAULT NULL COMMENT '(DC2Type:json)',
    ADD COLUMN IF NOT EXISTS `patent_analyzed_at` DATETIME NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `note_moyenne` DECIMAL(5,2) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `date_evaluation` DATETIME NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `evaluation_automatique` TINYINT(1) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `commentaire_admin` TEXT NULL DEFAULT NULL;

-- 9. TABLE: notifications (new)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `id_utilisateur` INT NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `message` TEXT NOT NULL,
    `lien` VARCHAR(255) NULL DEFAULT NULL,
    `lu` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `IDX_NOTIF_USER` (`id_utilisateur`),
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateurs` (`id_utilisateur`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- END OF MIGRATION
-- ============================================================
