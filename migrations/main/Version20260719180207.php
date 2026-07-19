<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Florimond\MultiDbMigrationsBundle\Migrations\CustomMigration;

/**
 * Aligne les index de trusted_device sur les noms générés par Doctrine.
 */
final class Version20260719180207 extends CustomMigration
{
    protected ?string $targetDatabase = 'main';

    public function getDescription(): string
    {
        return 'Renomme les index de trusted_device selon la convention Doctrine (diff vide ensuite).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE trusted_device RENAME INDEX idx_td_user TO IDX_F37E8F7BA76ED395');
        $this->addSql('ALTER TABLE trusted_device RENAME INDEX idx_td_expires TO IDX_F37E8F7BF9D83E2');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE trusted_device RENAME INDEX idx_f37e8f7bf9d83e2 TO IDX_TD_EXPIRES');
        $this->addSql('ALTER TABLE trusted_device RENAME INDEX idx_f37e8f7ba76ed395 TO IDX_TD_USER');
    }
}
