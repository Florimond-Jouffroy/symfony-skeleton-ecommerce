<?php

declare(strict_types=1);

namespace DoctrineMigrations\Log;

use Doctrine\DBAL\Schema\Schema;
use Florimond\MultiDbMigrationsBundle\Migrations\CustomMigration;

final class Version20260000000001InitialSchema extends CustomMigration
{
    protected ?string $targetDatabase = 'log';

    public function getDescription(): string
    {
        return 'Initial schema — application log table (florimond/log-bundle)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_logs (id INT AUTO_INCREMENT NOT NULL, app_name VARCHAR(255) NOT NULL, channel VARCHAR(255) NOT NULL, level VARCHAR(10) NOT NULL, message LONGTEXT NOT NULL, context JSON DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_app_name (app_name), INDEX IDX_level (level), INDEX IDX_created_at (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS app_logs');
    }
}
