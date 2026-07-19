<?php

declare(strict_types=1);

namespace DoctrineMigrations\Log;

use Doctrine\DBAL\Schema\Schema;
use Florimond\MultiDbMigrationsBundle\Migrations\CustomMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260716160349 extends CustomMigration
{
    protected ?string $targetDatabase = 'log';

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE auth_logs (id INT AUTO_INCREMENT NOT NULL, app_name VARCHAR(255) NOT NULL, event VARCHAR(20) NOT NULL, user_identifier VARCHAR(255) DEFAULT NULL, ip VARCHAR(50) DEFAULT NULL, user_agent VARCHAR(512) DEFAULT NULL, failure_reason VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_auth_logs_app_name (app_name), INDEX IDX_auth_logs_event (event), INDEX IDX_auth_logs_user (user_identifier), INDEX IDX_auth_logs_created_at (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE auth_logs');
    }
}
