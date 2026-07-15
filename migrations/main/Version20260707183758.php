<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Florimond\MultiDbMigrationsBundle\Migrations\CustomMigration;

final class Version20260707183758 extends CustomMigration
{
    protected ?string $targetDatabase = 'main';

    public function getDescription(): string
    {
        return 'Add email verification to user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD is_verified TINYINT NOT NULL DEFAULT 0, ADD verification_token VARCHAR(64) DEFAULT NULL');
        // Les comptes existants sont considérés comme déjà vérifiés
        $this->addSql('UPDATE user SET is_verified = 1 WHERE is_verified = 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP is_verified, DROP verification_token');
    }
}
