<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260714110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création des tables support_ticket et support_message';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE support_ticket (
                id           INT AUTO_INCREMENT NOT NULL,
                user_id      INT DEFAULT NULL,
                subject      VARCHAR(255) NOT NULL,
                status       VARCHAR(20) NOT NULL DEFAULT \'open\',
                guest_name   VARCHAR(100) DEFAULT NULL,
                guest_email  VARCHAR(180) DEFAULT NULL,
                token        VARCHAR(64) NOT NULL,
                created_at   DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                updated_at   DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                UNIQUE INDEX UNIQ_SUPPORT_TOKEN (token),
                INDEX IDX_SUPPORT_USER (user_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        $this->addSql('
            CREATE TABLE support_message (
                id          INT AUTO_INCREMENT NOT NULL,
                ticket_id   INT NOT NULL,
                body        LONGTEXT NOT NULL,
                is_from_admin TINYINT(1) NOT NULL DEFAULT 0,
                author_name VARCHAR(100) NOT NULL,
                created_at  DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX IDX_SUPPORT_MSG_TICKET (ticket_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        $this->addSql('
            ALTER TABLE support_ticket
                ADD CONSTRAINT FK_SUPPORT_TICKET_USER
                FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL
        ');

        $this->addSql('
            ALTER TABLE support_message
                ADD CONSTRAINT FK_SUPPORT_MESSAGE_TICKET
                FOREIGN KEY (ticket_id) REFERENCES support_ticket (id) ON DELETE CASCADE
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE support_message DROP FOREIGN KEY FK_SUPPORT_MESSAGE_TICKET');
        $this->addSql('ALTER TABLE support_ticket DROP FOREIGN KEY FK_SUPPORT_TICKET_USER');
        $this->addSql('DROP TABLE support_message');
        $this->addSql('DROP TABLE support_ticket');
    }
}
