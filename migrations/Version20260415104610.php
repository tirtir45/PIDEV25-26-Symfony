<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260415104610 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE commentaires (id INT AUTO_INCREMENT NOT NULL, contenu LONGTEXT NOT NULL, type VARCHAR(50) DEFAULT \'commentaire\', created_at DATETIME NOT NULL, id_tache INT NOT NULL, id_auteur INT NOT NULL, INDEX IDX_D9BEC0C47D026145 (id_tache), INDEX IDX_D9BEC0C4236D04AD (id_auteur), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE fichiers_projet (id INT AUTO_INCREMENT NOT NULL, nom_original VARCHAR(255) NOT NULL, nom_fichier VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, taille INT NOT NULL, extension VARCHAR(10) NOT NULL, uploaded_at DATETIME NOT NULL, id_projet INT NOT NULL, id_uploaded_by INT NOT NULL, INDEX IDX_DC6CF00476222944 (id_projet), INDEX IDX_DC6CF004FBF5FCD1 (id_uploaded_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE notifications (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, message LONGTEXT NOT NULL, lien VARCHAR(255) DEFAULT NULL, lu TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, id_utilisateur INT NOT NULL, INDEX IDX_6000B0D350EAE44 (id_utilisateur), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE ressources_projet (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, type VARCHAR(50) NOT NULL, emplacement VARCHAR(100) DEFAULT NULL, statut VARCHAR(20) DEFAULT \'disponible\' NOT NULL, caracteristiques JSON DEFAULT NULL, created_at DATETIME NOT NULL, id_projet INT NOT NULL, INDEX IDX_B2C582D176222944 (id_projet), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE commentaires ADD CONSTRAINT FK_D9BEC0C47D026145 FOREIGN KEY (id_tache) REFERENCES taches (id_tache) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commentaires ADD CONSTRAINT FK_D9BEC0C4236D04AD FOREIGN KEY (id_auteur) REFERENCES utilisateurs (id_utilisateur)');
        $this->addSql('ALTER TABLE fichiers_projet ADD CONSTRAINT FK_DC6CF00476222944 FOREIGN KEY (id_projet) REFERENCES projets (id_projet) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE fichiers_projet ADD CONSTRAINT FK_DC6CF004FBF5FCD1 FOREIGN KEY (id_uploaded_by) REFERENCES utilisateurs (id_utilisateur)');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D350EAE44 FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs (id_utilisateur) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ressources_projet ADD CONSTRAINT FK_B2C582D176222944 FOREIGN KEY (id_projet) REFERENCES projets (id_projet) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE subtasks DROP FOREIGN KEY `FK_2AEB8A4DFFFE75C0`');
        $this->addSql('ALTER TABLE task_comments DROP FOREIGN KEY `FK_1F5E7C668DB60186`');
        $this->addSql('ALTER TABLE task_comments DROP FOREIGN KEY `FK_1F5E7C66A76ED395`');
        $this->addSql('DROP TABLE subtasks');
        $this->addSql('DROP TABLE task_comments');
        $this->addSql('ALTER TABLE taches DROP FOREIGN KEY `FK_3BF2CD984A40C0F0`');
        $this->addSql('ALTER TABLE taches DROP FOREIGN KEY `FK_3BF2CD98C9B7E05A`');
        $this->addSql('ALTER TABLE taches DROP FOREIGN KEY `FK_TACHES_ASSIGNEE`');
        $this->addSql('ALTER TABLE taches DROP FOREIGN KEY `FK_TACHES_CREATED_BY`');
        $this->addSql('DROP INDEX IDX_3BF2CD9859EC7D60 ON taches');
        $this->addSql('DROP INDEX IDX_3BF2CD98DE12AB56 ON taches');
        $this->addSql('ALTER TABLE taches DROP priorite, DROP story_points, DROP tags, DROP checklist_approved, DROP rejection_reason, DROP progress_percentage, DROP completed_at, DROP updated_at, DROP deleted_at, DROP assignee_id, DROP created_by, DROP created_at, CHANGE titre titre VARCHAR(150) DEFAULT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE statut statut VARCHAR(255) DEFAULT \'À faire\' NOT NULL, CHANGE date_limite date_limite DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE taches ADD CONSTRAINT FK_3BF2CD984A40C0F0 FOREIGN KEY (id_responsable) REFERENCES utilisateurs (id_utilisateur) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE taches ADD CONSTRAINT FK_3BF2CD98C9B7E05A FOREIGN KEY (id_sprint) REFERENCES sprints (id_sprint) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE subtasks (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(200) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, is_completed TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, parent_task_id INT NOT NULL, INDEX IDX_2AEB8A4DFFFE75C0 (parent_task_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE task_comments (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, task_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_1F5E7C668DB60186 (task_id), INDEX IDX_1F5E7C66A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE subtasks ADD CONSTRAINT `FK_2AEB8A4DFFFE75C0` FOREIGN KEY (parent_task_id) REFERENCES taches (id_tache) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_comments ADD CONSTRAINT `FK_1F5E7C668DB60186` FOREIGN KEY (task_id) REFERENCES taches (id_tache) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_comments ADD CONSTRAINT `FK_1F5E7C66A76ED395` FOREIGN KEY (user_id) REFERENCES utilisateurs (id_utilisateur)');
        $this->addSql('ALTER TABLE commentaires DROP FOREIGN KEY FK_D9BEC0C47D026145');
        $this->addSql('ALTER TABLE commentaires DROP FOREIGN KEY FK_D9BEC0C4236D04AD');
        $this->addSql('ALTER TABLE fichiers_projet DROP FOREIGN KEY FK_DC6CF00476222944');
        $this->addSql('ALTER TABLE fichiers_projet DROP FOREIGN KEY FK_DC6CF004FBF5FCD1');
        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY FK_6000B0D350EAE44');
        $this->addSql('ALTER TABLE ressources_projet DROP FOREIGN KEY FK_B2C582D176222944');
        $this->addSql('DROP TABLE commentaires');
        $this->addSql('DROP TABLE fichiers_projet');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE ressources_projet');
        $this->addSql('ALTER TABLE taches DROP FOREIGN KEY FK_3BF2CD98C9B7E05A');
        $this->addSql('ALTER TABLE taches DROP FOREIGN KEY FK_3BF2CD984A40C0F0');
        $this->addSql('ALTER TABLE taches ADD priorite VARCHAR(20) DEFAULT \'MEDIUM\' NOT NULL, ADD story_points INT DEFAULT NULL, ADD tags JSON DEFAULT NULL, ADD checklist_approved TINYINT DEFAULT 0 NOT NULL, ADD rejection_reason LONGTEXT DEFAULT NULL, ADD progress_percentage INT DEFAULT 0 NOT NULL, ADD completed_at DATETIME DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL, ADD deleted_at DATETIME DEFAULT NULL, ADD assignee_id INT DEFAULT NULL, ADD created_by INT DEFAULT NULL, ADD created_at DATETIME NOT NULL, CHANGE titre titre VARCHAR(150) NOT NULL, CHANGE description description LONGTEXT NOT NULL, CHANGE statut statut VARCHAR(20) DEFAULT \'TODO\' NOT NULL, CHANGE date_limite date_limite DATE NOT NULL');
        $this->addSql('ALTER TABLE taches ADD CONSTRAINT `FK_3BF2CD98C9B7E05A` FOREIGN KEY (id_sprint) REFERENCES sprints (id_sprint) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE taches ADD CONSTRAINT `FK_3BF2CD984A40C0F0` FOREIGN KEY (id_responsable) REFERENCES utilisateurs (id_utilisateur) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE taches ADD CONSTRAINT `FK_TACHES_ASSIGNEE` FOREIGN KEY (assignee_id) REFERENCES membres_equipe (id_membre) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE taches ADD CONSTRAINT `FK_TACHES_CREATED_BY` FOREIGN KEY (created_by) REFERENCES utilisateurs (id_utilisateur)');
        $this->addSql('CREATE INDEX IDX_3BF2CD9859EC7D60 ON taches (assignee_id)');
        $this->addSql('CREATE INDEX IDX_3BF2CD98DE12AB56 ON taches (created_by)');
    }
}
