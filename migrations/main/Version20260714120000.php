<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260714120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table static_page';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE static_page (
                id         INT AUTO_INCREMENT NOT NULL,
                title      VARCHAR(255) NOT NULL,
                slug       VARCHAR(255) NOT NULL,
                content    JSON NOT NULL,
                is_active  TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                UNIQUE INDEX UNIQ_STATIC_PAGE_SLUG (slug),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE static_page');
    }
}
