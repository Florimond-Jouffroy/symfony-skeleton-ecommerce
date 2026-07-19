<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Florimond\MultiDbMigrationsBundle\Migrations\CustomMigration;

/**
 * Lie une demande de retour à son fil de discussion support.
 */
final class Version20260719094831 extends CustomMigration
{
    protected ?string $targetDatabase = 'main';

    public function getDescription(): string
    {
        return 'Ajoute return_request.support_ticket_id (fil de discussion du retour).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE return_request ADD support_ticket_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE return_request ADD CONSTRAINT FK_2DBF9D40C6D2DC64 FOREIGN KEY (support_ticket_id) REFERENCES support_ticket (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2DBF9D40C6D2DC64 ON return_request (support_ticket_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE return_request DROP FOREIGN KEY FK_2DBF9D40C6D2DC64');
        $this->addSql('DROP INDEX UNIQ_2DBF9D40C6D2DC64 ON return_request');
        $this->addSql('ALTER TABLE return_request DROP support_ticket_id');
    }
}
