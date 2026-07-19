<?php

declare(strict_types=1);

namespace DoctrineMigrations\Log;

use Doctrine\DBAL\Schema\Schema;
use Florimond\MultiDbMigrationsBundle\Migrations\CustomMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260716160041 extends CustomMigration
{
    protected ?string $targetDatabase = 'log';

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE error_logs (id INT AUTO_INCREMENT NOT NULL, app_name VARCHAR(255) NOT NULL, level VARCHAR(10) NOT NULL, exception_class VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, file VARCHAR(512) NOT NULL, line INT NOT NULL, stacktrace LONGTEXT NOT NULL, channel VARCHAR(50) NOT NULL, uri VARCHAR(1024) DEFAULT NULL, method VARCHAR(10) DEFAULT NULL, route VARCHAR(255) DEFAULT NULL, referer VARCHAR(1024) DEFAULT NULL, user_agent VARCHAR(512) DEFAULT NULL, ip VARCHAR(50) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_error_logs_app_name (app_name), INDEX IDX_error_logs_level (level), INDEX IDX_error_logs_created_at (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('DROP INDEX IDX_level ON app_logs');
        $this->addSql('DROP INDEX IDX_created_at ON app_logs');
        $this->addSql('DROP INDEX IDX_app_name ON app_logs');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE error_logs');
        $this->addSql('CREATE INDEX IDX_level ON app_logs (level)');
        $this->addSql('CREATE INDEX IDX_created_at ON app_logs (created_at)');
        $this->addSql('CREATE INDEX IDX_app_name ON app_logs (app_name)');
    }
}
