<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Florimond\MultiDbMigrationsBundle\Migrations\CustomMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715174658 extends CustomMigration
{
    protected ?string $targetDatabase = 'main';

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_logs (id INT AUTO_INCREMENT NOT NULL, app_name VARCHAR(255) NOT NULL, channel VARCHAR(255) NOT NULL, level VARCHAR(10) NOT NULL, message LONGTEXT NOT NULL, context JSON DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE system_configuration (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, value VARCHAR(255) NOT NULL, is_dev TINYINT DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_DB40EA425E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('DROP INDEX IDX_created_at ON activity_log');
        $this->addSql('DROP INDEX IDX_entity_type ON activity_log');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE app_logs');
        $this->addSql('DROP TABLE system_configuration');
        $this->addSql('CREATE INDEX IDX_created_at ON activity_log (created_at)');
        $this->addSql('CREATE INDEX IDX_entity_type ON activity_log (entity_type)');
    }
}
