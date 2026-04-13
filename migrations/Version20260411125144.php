<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260411125144 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE projets ADD budget_estime NUMERIC(10, 2) NOT NULL, ADD duree_estimee INT NOT NULL, ADD nb_membres_equipe INT NOT NULL, ADD statut_juridique VARCHAR(50) NOT NULL, ADD financement_actuel VARCHAR(20) NOT NULL, ADD partenaires_potentiels LONGTEXT DEFAULT NULL, ADD email_contact VARCHAR(255) NOT NULL, ADD telephone_contact VARCHAR(20) NOT NULL, ADD site_web VARCHAR(255) DEFAULT NULL, ADD experiences_anterieures LONGTEXT DEFAULT NULL, CHANGE titre titre VARCHAR(150) NOT NULL, CHANGE description description LONGTEXT NOT NULL, CHANGE secteur secteur VARCHAR(100) NOT NULL, CHANGE objectifs objectifs LONGTEXT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE projets DROP budget_estime, DROP duree_estimee, DROP nb_membres_equipe, DROP statut_juridique, DROP financement_actuel, DROP partenaires_potentiels, DROP email_contact, DROP telephone_contact, DROP site_web, DROP experiences_anterieures, CHANGE titre titre VARCHAR(150) DEFAULT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE secteur secteur VARCHAR(100) DEFAULT NULL, CHANGE objectifs objectifs LONGTEXT DEFAULT NULL');
    }
}
