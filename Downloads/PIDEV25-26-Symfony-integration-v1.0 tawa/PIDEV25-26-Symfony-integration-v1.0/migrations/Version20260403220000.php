<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add created_at column to ressources';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ressources ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ressources DROP COLUMN IF EXISTS created_at');
    }
}
