<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crée la table processed_webhook_event (idempotence des webhooks de paiement).
 */
final class Version20260718212506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add processed_webhook_event table for payment webhook idempotency';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE processed_webhook_event (id INT AUTO_INCREMENT NOT NULL, provider VARCHAR(32) NOT NULL, event_id VARCHAR(255) NOT NULL, processed_at DATETIME NOT NULL, UNIQUE INDEX uniq_webhook_provider_event (provider, event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE processed_webhook_event');
    }
}
