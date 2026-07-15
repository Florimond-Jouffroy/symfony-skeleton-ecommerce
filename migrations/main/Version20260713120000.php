<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shop.enabled default setting';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO app_setting (setting_key, value) VALUES ('shop.enabled', 'true') ON DUPLICATE KEY UPDATE value = value");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM app_setting WHERE setting_key = 'shop.enabled'");
    }
}
