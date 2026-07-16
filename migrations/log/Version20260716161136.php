<?php

declare(strict_types=1);

namespace DoctrineMigrations\Log;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260716161136 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE mail_logs (id INT AUTO_INCREMENT NOT NULL, app_name VARCHAR(255) NOT NULL, recipient LONGTEXT NOT NULL, subject VARCHAR(500) DEFAULT NULL, template VARCHAR(255) DEFAULT NULL, success TINYINT NOT NULL, error_message LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_mail_logs_app_name (app_name), INDEX IDX_mail_logs_success (success), INDEX IDX_mail_logs_created_at (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE mail_logs');
    }
}
