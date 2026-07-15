<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout de la table promo_code';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE promo_code (
                id              INT AUTO_INCREMENT NOT NULL,
                code            VARCHAR(50)  NOT NULL,
                type            VARCHAR(10)  NOT NULL DEFAULT \'percent\',
                value           INT          NOT NULL DEFAULT 0,
                expires_at      DATETIME     DEFAULT NULL,
                max_uses        INT          DEFAULT NULL,
                used_count      INT          NOT NULL DEFAULT 0,
                is_active       TINYINT(1)   NOT NULL DEFAULT 1,
                created_at      DATETIME     NOT NULL,
                UNIQUE INDEX UNIQ_promo_code (code),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE promo_code');
    }
}
