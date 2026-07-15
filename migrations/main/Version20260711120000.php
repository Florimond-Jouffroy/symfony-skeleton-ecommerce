<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Florimond\MultiDbMigrationsBundle\Migrations\CustomMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260711120000 extends CustomMigration
{
    protected ?string $targetDatabase = 'main';

    public function getDescription(): string
    {
        return 'Add cover_image column to article table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article ADD cover_image VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article DROP COLUMN cover_image');
    }
}
