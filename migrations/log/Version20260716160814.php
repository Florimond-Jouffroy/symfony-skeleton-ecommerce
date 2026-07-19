<?php

declare(strict_types=1);

namespace DoctrineMigrations\Log;

use Doctrine\DBAL\Schema\Schema;
use Florimond\MultiDbMigrationsBundle\Migrations\CustomMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260716160814 extends CustomMigration
{
    protected ?string $targetDatabase = 'log';

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE http_logs (id INT AUTO_INCREMENT NOT NULL, app_name VARCHAR(255) NOT NULL, method VARCHAR(10) NOT NULL, route VARCHAR(255) DEFAULT NULL, uri LONGTEXT NOT NULL, status_code INT NOT NULL, duration_ms INT DEFAULT NULL, ip VARCHAR(50) DEFAULT NULL, user_agent VARCHAR(512) DEFAULT NULL, user_identifier VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_http_logs_app_name (app_name), INDEX IDX_http_logs_status (status_code), INDEX IDX_http_logs_route (route), INDEX IDX_http_logs_created_at (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE http_logs');
    }
}
