<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Florimond\MultiDbMigrationsBundle\Migrations\CustomMigration;

final class Version20260711110000 extends CustomMigration
{
    protected ?string $targetDatabase = 'main';

    public function getDescription(): string
    {
        return 'Create media_file table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE media_file (id INT AUTO_INCREMENT NOT NULL, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(50) NOT NULL, size INT NOT NULL, hash VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', uploaded_by_id INT NOT NULL, UNIQUE INDEX UNIQ_media_hash (hash), INDEX IDX_media_uploaded_by (uploaded_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE media_file ADD CONSTRAINT FK_media_uploaded_by FOREIGN KEY (uploaded_by_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media_file DROP FOREIGN KEY FK_media_uploaded_by');
        $this->addSql('DROP TABLE media_file');
    }
}
