<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260714140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table activity_log pour le journal d\'activité admin';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE activity_log (
                id                  INT AUTO_INCREMENT NOT NULL,
                action              VARCHAR(100) NOT NULL,
                entity_type         VARCHAR(50) NOT NULL,
                entity_id           INT DEFAULT NULL,
                entity_label        VARCHAR(255) NOT NULL,
                context             JSON NOT NULL,
                performed_by_email  VARCHAR(255) NOT NULL,
                created_at          DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX IDX_AL_ENTITY_TYPE (entity_type),
                INDEX IDX_AL_CREATED_AT (created_at),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE activity_log');
    }
}
