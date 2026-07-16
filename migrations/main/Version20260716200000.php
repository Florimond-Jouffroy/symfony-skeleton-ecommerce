<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260716200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add trusted_device table for 2FA remember-device feature';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE trusted_device (
                id INT AUTO_INCREMENT NOT NULL,
                user_id INT NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                expires_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX IDX_TD_USER (user_id),
                INDEX IDX_TD_EXPIRES (expires_at),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE trusted_device
                ADD CONSTRAINT FK_TD_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE trusted_device DROP FOREIGN KEY FK_TD_USER');
        $this->addSql('DROP TABLE trusted_device');
    }
}
