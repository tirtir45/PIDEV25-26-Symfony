<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260404204112 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation_participants DROP FOREIGN KEY `conversation_participants_ibfk_1`');
        $this->addSql('ALTER TABLE conversation_participants DROP FOREIGN KEY `conversation_participants_ibfk_2`');
        $this->addSql('ALTER TABLE daily_scrums DROP FOREIGN KEY `daily_scrums_ibfk_1`');
        $this->addSql('ALTER TABLE demandes_ressources DROP FOREIGN KEY `demandes_ressources_ibfk_1`');
        $this->addSql('ALTER TABLE demandes_ressources DROP FOREIGN KEY `demandes_ressources_ibfk_2`');
        $this->addSql('ALTER TABLE demandes_verification DROP FOREIGN KEY `demandes_verification_ibfk_1`');
        $this->addSql('ALTER TABLE demandes_verification DROP FOREIGN KEY `demandes_verification_ibfk_2`');
        $this->addSql('ALTER TABLE demande_emploi DROP FOREIGN KEY `demande_emploi_ibfk_1`');
        $this->addSql('ALTER TABLE demande_emploi DROP FOREIGN KEY `demande_emploi_ibfk_2`');
        $this->addSql('ALTER TABLE evaluations_projet DROP FOREIGN KEY `evaluations_projet_ibfk_1`');
        $this->addSql('ALTER TABLE evaluations_projet DROP FOREIGN KEY `evaluations_projet_ibfk_2`');
        $this->addSql('ALTER TABLE historique_connexions DROP FOREIGN KEY `historique_connexions_ibfk_1`');
        $this->addSql('ALTER TABLE membres_equipe DROP FOREIGN KEY `membres_equipe_ibfk_1`');
        $this->addSql('ALTER TABLE membres_equipe DROP FOREIGN KEY `membres_equipe_ibfk_2`');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY `messages_ibfk_1`');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY `messages_ibfk_2`');
        $this->addSql('ALTER TABLE password_reset_tokens DROP FOREIGN KEY `password_reset_tokens_ibfk_1`');
        $this->addSql('ALTER TABLE projets DROP FOREIGN KEY `projets_ibfk_1`');
        $this->addSql('ALTER TABLE reclamations DROP FOREIGN KEY `reclamations_ibfk_1`');
        $this->addSql('ALTER TABLE reclamation_commentaires DROP FOREIGN KEY `fk_commentaire_auteur`');
        $this->addSql('ALTER TABLE reclamation_commentaires DROP FOREIGN KEY `reclamation_commentaires_ibfk_1`');
        $this->addSql('ALTER TABLE reservations DROP FOREIGN KEY `reservations_ibfk_1`');
        $this->addSql('ALTER TABLE reservations DROP FOREIGN KEY `reservations_ibfk_2`');
        $this->addSql('ALTER TABLE ressources DROP FOREIGN KEY `ressources_ibfk_1`');
        $this->addSql('ALTER TABLE sprints DROP FOREIGN KEY `sprints_ibfk_1`');
        $this->addSql('ALTER TABLE taches DROP FOREIGN KEY `taches_ibfk_1`');
        $this->addSql('ALTER TABLE taches DROP FOREIGN KEY `taches_ibfk_2`');
        $this->addSql('ALTER TABLE taches DROP FOREIGN KEY `taches_ibfk_3`');
        $this->addSql('ALTER TABLE userstory DROP FOREIGN KEY `userstory_ibfk_1`');
        $this->addSql('ALTER TABLE userstory DROP FOREIGN KEY `userstory_ibfk_2`');
        $this->addSql('ALTER TABLE user_stories DROP FOREIGN KEY `fk_user_stories_membre_equipe`');
        $this->addSql('ALTER TABLE user_stories DROP FOREIGN KEY `user_stories_ibfk_1`');
        $this->addSql('ALTER TABLE user_stories DROP FOREIGN KEY `user_stories_ibfk_2`');
        $this->addSql('ALTER TABLE user_story_assignments DROP FOREIGN KEY `user_story_assignments_ibfk_1`');
        $this->addSql('ALTER TABLE user_story_assignments DROP FOREIGN KEY `user_story_assignments_ibfk_2`');
        $this->addSql('DROP TABLE commandes');
        $this->addSql('DROP TABLE conversations');
        $this->addSql('DROP TABLE conversation_participants');
        $this->addSql('DROP TABLE criteres_evaluation');
        $this->addSql('DROP TABLE daily_scrums');
        $this->addSql('DROP TABLE demandes_ressources');
        $this->addSql('DROP TABLE demandes_verification');
        $this->addSql('DROP TABLE demande_emploi');
        $this->addSql('DROP TABLE evaluations_projet');
        $this->addSql('DROP TABLE evenements');
        $this->addSql('DROP TABLE historique_connexions');
        $this->addSql('DROP TABLE lignes_commande');
        $this->addSql('DROP TABLE membres_equipe');
        $this->addSql('DROP TABLE messages');
        $this->addSql('DROP TABLE password_reset_tokens');
        $this->addSql('DROP TABLE projets');
        $this->addSql('DROP TABLE publication');
        $this->addSql('DROP TABLE reclamations');
        $this->addSql('DROP TABLE reclamation_commentaires');
        $this->addSql('DROP TABLE reservations');
        $this->addSql('DROP TABLE ressources');
        $this->addSql('DROP TABLE sprints');
        $this->addSql('DROP TABLE taches');
        $this->addSql('DROP TABLE userstory');
        $this->addSql('DROP TABLE user_stories');
        $this->addSql('DROP TABLE user_story_assignments');
        $this->addSql('DROP INDEX nom_role ON roles');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B63E2EC7A5B94004 ON roles (nom_role)');
        $this->addSql('ALTER TABLE utilisateurs DROP FOREIGN KEY `utilisateurs_ibfk_1`');
        $this->addSql('ALTER TABLE utilisateurs DROP date_verification, DROP latitude, DROP longitude, DROP badge_verification, CHANGE bio bio LONGTEXT DEFAULT NULL, CHANGE competences competences LONGTEXT DEFAULT NULL, CHANGE date_inscription date_inscription DATETIME DEFAULT NULL, CHANGE badge_verifie badge_verifie TINYINT DEFAULT 0 NOT NULL, CHANGE profil_complet profil_complet TINYINT DEFAULT 0 NOT NULL, CHANGE actif actif TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('DROP INDEX email ON utilisateurs');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_497B315EE7927C74 ON utilisateurs (email)');
        $this->addSql('DROP INDEX id_role ON utilisateurs');
        $this->addSql('CREATE INDEX IDX_497B315EDC499668 ON utilisateurs (id_role)');
        $this->addSql('ALTER TABLE utilisateurs ADD CONSTRAINT `utilisateurs_ibfk_1` FOREIGN KEY (id_role) REFERENCES roles (id_role)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE commandes (id_commande INT AUTO_INCREMENT NOT NULL, id_entrepreneur INT NOT NULL, statut VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT \'Panier\' COLLATE `utf8mb4_general_ci`, date_creation DATETIME DEFAULT CURRENT_TIMESTAMP, total_global FLOAT DEFAULT \'0\', tracking_number VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, carrier_code VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX fk_commande_user (id_entrepreneur), PRIMARY KEY (id_commande)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE conversations (id_conversation INT AUTO_INCREMENT NOT NULL, type ENUM(\'individuelle\', \'groupe\') CHARACTER SET utf8mb4 DEFAULT \'individuelle\' COLLATE `utf8mb4_general_ci`, titre VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, date_creation DATETIME DEFAULT CURRENT_TIMESTAMP, derniere_activite DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id_conversation)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE conversation_participants (id_participation INT AUTO_INCREMENT NOT NULL, id_conversation INT DEFAULT NULL, id_utilisateur INT DEFAULT NULL, date_ajout DATETIME DEFAULT CURRENT_TIMESTAMP, est_archive TINYINT DEFAULT 0, dernier_message_lu INT DEFAULT NULL, INDEX id_utilisateur (id_utilisateur), INDEX idx_conversation_participants (id_conversation, id_utilisateur), INDEX IDX_21821ED3A94F539B (id_conversation), PRIMARY KEY (id_participation)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE criteres_evaluation (id_critere INT AUTO_INCREMENT NOT NULL, nom_critere VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, poids INT DEFAULT 1, actif TINYINT DEFAULT 1, PRIMARY KEY (id_critere)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE daily_scrums (id_daily INT AUTO_INCREMENT NOT NULL, id_sprint INT NOT NULL, date DATE NOT NULL, points_restants INT NOT NULL, commentaires TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, UNIQUE INDEX unique_sprint_date (id_sprint, date), INDEX IDX_E98B133DC9B7E05A (id_sprint), PRIMARY KEY (id_daily)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE demandes_ressources (id_demande INT AUTO_INCREMENT NOT NULL, id_ressource INT DEFAULT NULL, id_entrepreneur INT DEFAULT NULL, statut ENUM(\'En attente\', \'Acceptée\', \'Refusée\') CHARACTER SET utf8mb4 DEFAULT \'En attente\' COLLATE `utf8mb4_general_ci`, date_demande DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX id_entrepreneur (id_entrepreneur), INDEX id_ressource (id_ressource), PRIMARY KEY (id_demande)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE demandes_verification (id_demande INT AUTO_INCREMENT NOT NULL, id_utilisateur INT NOT NULL, type_role VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT \'Candidat\' NOT NULL COLLATE `utf8mb4_general_ci`, statut ENUM(\'en_attente\', \'approuvee\', \'refusee\') CHARACTER SET utf8mb4 DEFAULT \'en_attente\' COLLATE `utf8mb4_general_ci`, raison_demande TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, document_justificatif VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, date_demande DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, date_traitement DATETIME DEFAULT NULL, id_admin_traitant INT DEFAULT NULL, commentaire_admin TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX idx_statut (statut), INDEX idx_utilisateur (id_utilisateur), INDEX idx_date (date_demande), INDEX id_admin_traitant (id_admin_traitant), PRIMARY KEY (id_demande)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE demande_emploi (demande_id INT AUTO_INCREMENT NOT NULL, publication_id INT NOT NULL, candidat_id INT NOT NULL, cv_url VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, lettre_motivation TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, motivation_ciblee TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, statut_demande ENUM(\'en_attente\', \'retenu\', \'refusé\', \'entretien\') CHARACTER SET utf8mb4 DEFAULT \'en_attente\' COLLATE `utf8mb4_general_ci`, date_demande DATE DEFAULT CURRENT_DATE, INDEX publication_id (publication_id), INDEX candidat_id (candidat_id), PRIMARY KEY (demande_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE evaluations_projet (id_evaluation INT AUTO_INCREMENT NOT NULL, id_projet INT DEFAULT NULL, id_admin INT DEFAULT NULL, note_originalite INT DEFAULT NULL, note_faisabilite INT DEFAULT NULL, note_impact INT DEFAULT NULL, note_clarte INT DEFAULT NULL, note_budget INT DEFAULT NULL, commentaire TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, date_evaluation DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX id_projet (id_projet), INDEX id_admin (id_admin), PRIMARY KEY (id_evaluation)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE evenements (id_evenement INT AUTO_INCREMENT NOT NULL, titre VARCHAR(150) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, date_evenement DATE DEFAULT NULL, lieu VARCHAR(150) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, capacite INT DEFAULT NULL, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, prix NUMERIC(10, 2) DEFAULT NULL, PRIMARY KEY (id_evenement)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE historique_connexions (id_historique INT AUTO_INCREMENT NOT NULL, id_utilisateur INT NOT NULL, adresse_ip VARCHAR(45) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, ville VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, pays VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, region VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, code_postal VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, timezone VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, date_connexion DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, user_agent TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX idx_adresse_ip (adresse_ip), INDEX idx_utilisateur (id_utilisateur), INDEX idx_date_connexion (date_connexion), PRIMARY KEY (id_historique)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE lignes_commande (id_ligne INT AUTO_INCREMENT NOT NULL, id_commande INT NOT NULL, id_ressource INT NOT NULL, quantite INT DEFAULT 1 NOT NULL, date_deb DATE DEFAULT NULL, date_fin DATE DEFAULT NULL, prix_ligne FLOAT DEFAULT NULL, INDEX id_ressource (id_ressource), INDEX id_commande (id_commande), PRIMARY KEY (id_ligne)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE membres_equipe (id_membre INT AUTO_INCREMENT NOT NULL, id_projet INT DEFAULT NULL, id_utilisateur INT DEFAULT NULL, role_equipe VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX id_utilisateur (id_utilisateur), INDEX id_projet (id_projet), PRIMARY KEY (id_membre)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE messages (id_message INT AUTO_INCREMENT NOT NULL, id_conversation INT DEFAULT NULL, id_expediteur INT DEFAULT NULL, contenu TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP, est_lu TINYINT DEFAULT 0, est_modifie TINYINT DEFAULT 0, est_supprime TINYINT DEFAULT 0, type_contenu ENUM(\'texte\', \'image\', \'fichier\') CHARACTER SET utf8mb4 DEFAULT \'texte\' COLLATE `utf8mb4_general_ci`, piece_jointe VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX id_expediteur (id_expediteur), INDEX idx_messages_conversation (id_conversation, date_envoi), INDEX IDX_DB021E96A94F539B (id_conversation), PRIMARY KEY (id_message)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE password_reset_tokens (id INT AUTO_INCREMENT NOT NULL, id_utilisateur INT NOT NULL, token VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, date_creation DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, date_expiration DATETIME DEFAULT NULL, utilise TINYINT DEFAULT 0, INDEX id_utilisateur (id_utilisateur), INDEX idx_token (token), INDEX idx_expiration (date_expiration), UNIQUE INDEX token (token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE projets (id_projet INT AUTO_INCREMENT NOT NULL, id_entrepreneur INT DEFAULT NULL, titre VARCHAR(150) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, secteur VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, objectifs TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, etat ENUM(\'En attente\', \'Accepté\', \'Refusé\', \'En cours\', \'Terminé\') CHARACTER SET utf8mb4 DEFAULT \'En attente\' COLLATE `utf8mb4_general_ci`, commentaire_admin TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, date_soumission DATETIME DEFAULT CURRENT_TIMESTAMP, note_moyenne NUMERIC(3, 2) DEFAULT NULL, date_evaluation DATETIME DEFAULT NULL, evaluation_automatique TINYINT DEFAULT 0, question1_answer VARCHAR(500) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, question2_answer VARCHAR(500) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, question3_answer VARCHAR(500) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX id_entrepreneur (id_entrepreneur), PRIMARY KEY (id_projet)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE publication (publication_id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, date_publication DATE NOT NULL, date_expiration DATE DEFAULT NULL, type_contrat ENUM(\'CDI\', \'CDD\', \'Stage\', \'Freelance\') CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, departement VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, localisation VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, statut ENUM(\'actif\', \'expiré\', \'annulé\') CHARACTER SET utf8mb4 DEFAULT \'actif\' COLLATE `utf8mb4_general_ci`, PRIMARY KEY (publication_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE reclamations (id_reclamation INT AUTO_INCREMENT NOT NULL, id_utilisateur INT DEFAULT NULL, sujet VARCHAR(150) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, statut VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT \'EN_ATTENTE\' COLLATE `utf8mb4_general_ci`, date_creation DATETIME DEFAULT CURRENT_TIMESTAMP, adresse VARCHAR(500) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, latitude NUMERIC(10, 8) DEFAULT NULL, longitude NUMERIC(11, 8) DEFAULT NULL, emotion VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, reponse_admin TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, date_modification DATETIME DEFAULT NULL, INDEX id_utilisateur (id_utilisateur), PRIMARY KEY (id_reclamation)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE reclamation_commentaires (id_commentaire INT AUTO_INCREMENT NOT NULL, id_reclamation INT NOT NULL, id_auteur INT DEFAULT 1 NOT NULL, commentaire TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, date_commentaire DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX id_reclamation (id_reclamation), INDEX fk_commentaire_auteur (id_auteur), PRIMARY KEY (id_commentaire)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE reservations (id_reservation INT AUTO_INCREMENT NOT NULL, id_evenement INT DEFAULT NULL, id_utilisateur INT DEFAULT NULL, date_reservation DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX id_utilisateur (id_utilisateur), INDEX id_evenement (id_evenement), PRIMARY KEY (id_reservation)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE ressources (id_ressource INT AUTO_INCREMENT NOT NULL, id_fournisseur INT DEFAULT NULL, nom VARCHAR(150) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, offre VARCHAR(30) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, type_r VARCHAR(30) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, image_r VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, prix_achat FLOAT DEFAULT NULL, disponibilite TINYINT DEFAULT 1, prix_louer FLOAT DEFAULT NULL, unite_louer VARCHAR(30) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, quantite INT DEFAULT NULL, etat VARCHAR(30) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX id_fournisseur (id_fournisseur), PRIMARY KEY (id_ressource)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE sprints (id_sprint INT AUTO_INCREMENT NOT NULL, id_projet INT NOT NULL, nom VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, objectif TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, date_debut DATE NOT NULL, date_fin DATE NOT NULL, statut ENUM(\'À_venir\', \'Actif\', \'Terminé\', \'Annulé\') CHARACTER SET utf8mb4 DEFAULT \'À_venir\' COLLATE `utf8mb4_general_ci`, capacite_equipe INT DEFAULT 0, velocite_prevue INT DEFAULT 0, velocite_reelle INT DEFAULT 0, INDEX id_projet (id_projet), PRIMARY KEY (id_sprint)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE taches (id_tache INT AUTO_INCREMENT NOT NULL, id_projet INT DEFAULT NULL, id_sprint INT DEFAULT NULL, id_responsable INT DEFAULT NULL, titre VARCHAR(150) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, statut ENUM(\'À faire\', \'En cours\', \'Terminée\') CHARACTER SET utf8mb4 DEFAULT \'À faire\' COLLATE `utf8mb4_general_ci`, date_limite DATE DEFAULT NULL, INDEX id_responsable (id_responsable), INDEX id_sprint (id_sprint), INDEX id_projet (id_projet), PRIMARY KEY (id_tache)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE userstory (id_userstory INT AUTO_INCREMENT NOT NULL, id_projet INT NOT NULL, titre VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, story_points INT DEFAULT NULL, priorite ENUM(\'Critique\', \'Haute\', \'Moyenne\', \'Basse\') CHARACTER SET utf8mb4 DEFAULT \'Moyenne\' COLLATE `utf8mb4_general_ci`, statut ENUM(\'Backlog\', \'Prête\', \'En_cours\', \'Terminée\') CHARACTER SET utf8mb4 DEFAULT \'Backlog\' COLLATE `utf8mb4_general_ci`, date_creation DATETIME DEFAULT CURRENT_TIMESTAMP, id_sprint INT DEFAULT NULL, en_tant_que VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, je_veux VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, afin_de VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, criteres_acceptation TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX id_projet (id_projet), INDEX id_sprint (id_sprint), PRIMARY KEY (id_userstory)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE user_stories (id_user_story INT AUTO_INCREMENT NOT NULL, id_projet INT NOT NULL, id_sprint INT DEFAULT NULL, titre VARCHAR(200) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, en_tant_que VARCHAR(200) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, je_veux VARCHAR(500) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, afin_de VARCHAR(500) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, story_points INT DEFAULT 1, priorite ENUM(\'Critique\', \'Haute\', \'Moyenne\', \'Basse\') CHARACTER SET utf8mb4 DEFAULT \'Moyenne\' COLLATE `utf8mb4_general_ci`, statut ENUM(\'Backlog\', \'Prête\', \'En_cours\', \'Terminée\', \'Rejetée\') CHARACTER SET utf8mb4 DEFAULT \'Backlog\' COLLATE `utf8mb4_general_ci`, date_creation DATE NOT NULL, date_completion DATE DEFAULT NULL, criteres_acceptation TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, id_membre_equipe INT DEFAULT NULL, membre_nom VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, date_debut DATE DEFAULT NULL, INDEX id_sprint (id_sprint), INDEX fk_user_stories_membre_equipe (id_membre_equipe), INDEX id_projet (id_projet), PRIMARY KEY (id_user_story)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE user_story_assignments (id_assignment INT AUTO_INCREMENT NOT NULL, id_user_story INT NOT NULL, id_membre_equipe INT NOT NULL, date_assignment DATE NOT NULL, UNIQUE INDEX unique_assignment (id_user_story, id_membre_equipe), INDEX id_membre_equipe (id_membre_equipe), INDEX IDX_38BD8349AC1E7FB7 (id_user_story), PRIMARY KEY (id_assignment)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE conversation_participants ADD CONSTRAINT `conversation_participants_ibfk_1` FOREIGN KEY (id_conversation) REFERENCES conversations (id_conversation) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation_participants ADD CONSTRAINT `conversation_participants_ibfk_2` FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs (id_utilisateur) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE daily_scrums ADD CONSTRAINT `daily_scrums_ibfk_1` FOREIGN KEY (id_sprint) REFERENCES sprints (id_sprint) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE demandes_ressources ADD CONSTRAINT `demandes_ressources_ibfk_1` FOREIGN KEY (id_ressource) REFERENCES ressources (id_ressource)');
        $this->addSql('ALTER TABLE demandes_ressources ADD CONSTRAINT `demandes_ressources_ibfk_2` FOREIGN KEY (id_entrepreneur) REFERENCES utilisateurs (id_utilisateur)');
        $this->addSql('ALTER TABLE demandes_verification ADD CONSTRAINT `demandes_verification_ibfk_1` FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs (id_utilisateur) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE demandes_verification ADD CONSTRAINT `demandes_verification_ibfk_2` FOREIGN KEY (id_admin_traitant) REFERENCES utilisateurs (id_utilisateur) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE demande_emploi ADD CONSTRAINT `demande_emploi_ibfk_1` FOREIGN KEY (publication_id) REFERENCES publication (publication_id)');
        $this->addSql('ALTER TABLE demande_emploi ADD CONSTRAINT `demande_emploi_ibfk_2` FOREIGN KEY (candidat_id) REFERENCES utilisateurs (id_utilisateur)');
        $this->addSql('ALTER TABLE evaluations_projet ADD CONSTRAINT `evaluations_projet_ibfk_1` FOREIGN KEY (id_projet) REFERENCES projets (id_projet)');
        $this->addSql('ALTER TABLE evaluations_projet ADD CONSTRAINT `evaluations_projet_ibfk_2` FOREIGN KEY (id_admin) REFERENCES utilisateurs (id_utilisateur)');
        $this->addSql('ALTER TABLE historique_connexions ADD CONSTRAINT `historique_connexions_ibfk_1` FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs (id_utilisateur) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE membres_equipe ADD CONSTRAINT `membres_equipe_ibfk_1` FOREIGN KEY (id_projet) REFERENCES projets (id_projet)');
        $this->addSql('ALTER TABLE membres_equipe ADD CONSTRAINT `membres_equipe_ibfk_2` FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs (id_utilisateur)');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (id_conversation) REFERENCES conversations (id_conversation) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (id_expediteur) REFERENCES utilisateurs (id_utilisateur) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE password_reset_tokens ADD CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs (id_utilisateur) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE projets ADD CONSTRAINT `projets_ibfk_1` FOREIGN KEY (id_entrepreneur) REFERENCES utilisateurs (id_utilisateur)');
        $this->addSql('ALTER TABLE reclamations ADD CONSTRAINT `reclamations_ibfk_1` FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs (id_utilisateur)');
        $this->addSql('ALTER TABLE reclamation_commentaires ADD CONSTRAINT `fk_commentaire_auteur` FOREIGN KEY (id_auteur) REFERENCES utilisateurs (id_utilisateur) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reclamation_commentaires ADD CONSTRAINT `reclamation_commentaires_ibfk_1` FOREIGN KEY (id_reclamation) REFERENCES reclamations (id_reclamation) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservations ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (id_evenement) REFERENCES evenements (id_evenement)');
        $this->addSql('ALTER TABLE reservations ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs (id_utilisateur)');
        $this->addSql('ALTER TABLE ressources ADD CONSTRAINT `ressources_ibfk_1` FOREIGN KEY (id_fournisseur) REFERENCES utilisateurs (id_utilisateur)');
        $this->addSql('ALTER TABLE sprints ADD CONSTRAINT `sprints_ibfk_1` FOREIGN KEY (id_projet) REFERENCES projets (id_projet) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE taches ADD CONSTRAINT `taches_ibfk_1` FOREIGN KEY (id_projet) REFERENCES projets (id_projet)');
        $this->addSql('ALTER TABLE taches ADD CONSTRAINT `taches_ibfk_2` FOREIGN KEY (id_responsable) REFERENCES utilisateurs (id_utilisateur)');
        $this->addSql('ALTER TABLE taches ADD CONSTRAINT `taches_ibfk_3` FOREIGN KEY (id_sprint) REFERENCES sprints (id_sprint) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE userstory ADD CONSTRAINT `userstory_ibfk_1` FOREIGN KEY (id_projet) REFERENCES projets (id_projet)');
        $this->addSql('ALTER TABLE userstory ADD CONSTRAINT `userstory_ibfk_2` FOREIGN KEY (id_sprint) REFERENCES sprints (id_sprint)');
        $this->addSql('ALTER TABLE user_stories ADD CONSTRAINT `fk_user_stories_membre_equipe` FOREIGN KEY (id_membre_equipe) REFERENCES membres_equipe (id_membre) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE user_stories ADD CONSTRAINT `user_stories_ibfk_1` FOREIGN KEY (id_projet) REFERENCES projets (id_projet) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_stories ADD CONSTRAINT `user_stories_ibfk_2` FOREIGN KEY (id_sprint) REFERENCES sprints (id_sprint) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE user_story_assignments ADD CONSTRAINT `user_story_assignments_ibfk_1` FOREIGN KEY (id_user_story) REFERENCES user_stories (id_user_story) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_story_assignments ADD CONSTRAINT `user_story_assignments_ibfk_2` FOREIGN KEY (id_membre_equipe) REFERENCES membres_equipe (id_membre) ON DELETE CASCADE');
        $this->addSql('DROP INDEX uniq_b63e2ec7a5b94004 ON roles');
        $this->addSql('CREATE UNIQUE INDEX nom_role ON roles (nom_role)');
        $this->addSql('ALTER TABLE utilisateurs DROP FOREIGN KEY FK_497B315EDC499668');
        $this->addSql('ALTER TABLE utilisateurs ADD date_verification DATETIME DEFAULT NULL, ADD latitude NUMERIC(10, 8) DEFAULT NULL, ADD longitude NUMERIC(11, 8) DEFAULT NULL, ADD badge_verification TINYINT DEFAULT 0, CHANGE bio bio TEXT DEFAULT NULL, CHANGE competences competences TEXT DEFAULT NULL, CHANGE date_inscription date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE badge_verifie badge_verifie TINYINT DEFAULT 0, CHANGE profil_complet profil_complet TINYINT DEFAULT 0, CHANGE actif actif TINYINT DEFAULT 1');
        $this->addSql('DROP INDEX uniq_497b315ee7927c74 ON utilisateurs');
        $this->addSql('CREATE UNIQUE INDEX email ON utilisateurs (email)');
        $this->addSql('DROP INDEX idx_497b315edc499668 ON utilisateurs');
        $this->addSql('CREATE INDEX id_role ON utilisateurs (id_role)');
        $this->addSql('ALTER TABLE utilisateurs ADD CONSTRAINT FK_497B315EDC499668 FOREIGN KEY (id_role) REFERENCES roles (id_role)');
    }
}
