<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Florimond\MultiDbMigrationsBundle\Migrations\CustomMigration;

final class Version20260712110000 extends CustomMigration
{
    protected ?string $targetDatabase = 'main';

    public function getDescription(): string
    {
        return 'Add shipping_method table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE shipping_method (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            price INT NOT NULL DEFAULT 0,
            free_above_amount INT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            position INT NOT NULL DEFAULT 0,
            PRIMARY KEY(id),
            INDEX IDX_shipping_method_active (is_active)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE shipping_method');
    }
}
