<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260714100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table faq_item';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE faq_item (
                id         INT AUTO_INCREMENT NOT NULL,
                question   LONGTEXT NOT NULL,
                answer     LONGTEXT NOT NULL,
                position   INT NOT NULL DEFAULT 0,
                is_active  TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE faq_item');
    }
}
