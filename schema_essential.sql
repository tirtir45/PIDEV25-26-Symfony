SET FOREIGN_KEY_CHECKS=0;

-- Fix projets float columns
ALTER TABLE projets CHANGE ai_score_global ai_score_global DOUBLE PRECISION DEFAULT NULL, CHANGE market_score market_score DOUBLE PRECISION DEFAULT NULL, CHANGE patent_novelty_score patent_novelty_score DOUBLE PRECISION DEFAULT NULL;

-- Fix taches statut default and add FKs
ALTER TABLE taches DROP FOREIGN KEY FK_3BF2CD984A40C0F0;
ALTER TABLE taches DROP FOREIGN KEY FK_3BF2CD9876222944;
ALTER TABLE taches DROP FOREIGN KEY FK_3BF2CD98C9B7E05A;
ALTER TABLE taches CHANGE statut statut VARCHAR(255) DEFAULT 'A faire' NOT NULL;
DROP INDEX id_projet ON taches;
CREATE INDEX IDX_3BF2CD9876222944 ON taches (id_projet);
DROP INDEX id_sprint ON taches;
CREATE INDEX IDX_3BF2CD98C9B7E05A ON taches (id_sprint);
DROP INDEX id_responsable ON taches;
CREATE INDEX IDX_3BF2CD984A40C0F0 ON taches (id_responsable);
ALTER TABLE taches ADD CONSTRAINT FK_3BF2CD984A40C0F0 FOREIGN KEY (id_responsable) REFERENCES utilisateurs (id_utilisateur) ON DELETE CASCADE;
ALTER TABLE taches ADD CONSTRAINT FK_3BF2CD9876222944 FOREIGN KEY (id_projet) REFERENCES projets (id_projet) ON DELETE CASCADE;
ALTER TABLE taches ADD CONSTRAINT FK_3BF2CD98C9B7E05A FOREIGN KEY (id_sprint) REFERENCES sprints (id_sprint) ON DELETE CASCADE;

-- Fix notifications FK
ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D350EAE44 FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs (id_utilisateur) ON DELETE CASCADE;
CREATE INDEX IDX_6000B0D350EAE44 ON notifications (id_utilisateur);

-- Fix membres_equipe FKs
ALTER TABLE membres_equipe DROP FOREIGN KEY membres_equipe_ibfk_1;
ALTER TABLE membres_equipe DROP FOREIGN KEY membres_equipe_ibfk_2;
ALTER TABLE membres_equipe ADD CONSTRAINT FK_C184283276222944 FOREIGN KEY (id_projet) REFERENCES projets (id_projet) ON DELETE CASCADE;
ALTER TABLE membres_equipe ADD CONSTRAINT FK_C184283250EAE44 FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs (id_utilisateur) ON DELETE CASCADE;
DROP INDEX id_projet ON membres_equipe;
CREATE INDEX IDX_C184283276222944 ON membres_equipe (id_projet);
DROP INDEX id_utilisateur ON membres_equipe;
CREATE INDEX IDX_C184283250EAE44 ON membres_equipe (id_utilisateur);
ALTER TABLE membres_equipe ADD CONSTRAINT membres_equipe_ibfk_1 FOREIGN KEY (id_projet) REFERENCES projets (id_projet);
ALTER TABLE membres_equipe ADD CONSTRAINT membres_equipe_ibfk_2 FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs (id_utilisateur);

-- Fix sprints FK
ALTER TABLE sprints DROP FOREIGN KEY sprints_ibfk_1;
ALTER TABLE sprints CHANGE id_projet id_projet INT DEFAULT NULL, CHANGE objectif objectif LONGTEXT DEFAULT NULL, CHANGE statut statut VARCHAR(255) DEFAULT 'planifie' NOT NULL;
DROP INDEX id_projet ON sprints;
CREATE INDEX IDX_4EE4697176222944 ON sprints (id_projet);
ALTER TABLE sprints ADD CONSTRAINT sprints_ibfk_1 FOREIGN KEY (id_projet) REFERENCES projets (id_projet) ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS=1;
