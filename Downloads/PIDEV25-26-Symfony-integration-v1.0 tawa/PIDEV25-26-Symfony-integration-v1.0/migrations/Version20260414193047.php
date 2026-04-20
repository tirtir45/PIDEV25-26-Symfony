<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260414193047 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add low_stock_alert_sent column to ressources table';
    }

    public function up(Schema $schema): void
    {
        // Add only the required column for low stock alerts
        $this->addSql('ALTER TABLE ressources ADD low_stock_alert_sent TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove the column
        $this->addSql('ALTER TABLE ressources DROP low_stock_alert_sent');
    }
}
