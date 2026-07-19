<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Florimond\MultiDbMigrationsBundle\Migrations\CustomMigration;

/**
 * Ajoute la colonne `version` (verrou optimiste) sur product et product_variant.
 */
final class Version20260718203156 extends CustomMigration
{
    protected ?string $targetDatabase = 'main';

    public function getDescription(): string
    {
        return 'Add optimistic-lock version column to product and product_variant';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD version INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE product_variant ADD version INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP version');
        $this->addSql('ALTER TABLE product_variant DROP version');
    }
}
