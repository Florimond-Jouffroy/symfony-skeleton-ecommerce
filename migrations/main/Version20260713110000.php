<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tax_rate per product_category; replace tax_rate on invoice with tax_breakdown JSON; add default tax rate setting';
    }

    public function up(Schema $schema): void
    {
        // Taux TVA par catégorie de produit (null = utilise le taux global)
        $this->addSql('ALTER TABLE product_category ADD tax_rate INT DEFAULT NULL');

        // Remplace tax_rate (entier fixe) par tax_breakdown (JSON multi-taux)
        $this->addSql('ALTER TABLE invoice ADD tax_breakdown JSON NOT NULL DEFAULT (JSON_ARRAY())');

        // Backfill : encapsule l'ancien taux unique dans le nouveau format
        $this->addSql(<<<'SQL'
            UPDATE invoice
            SET tax_breakdown = JSON_ARRAY(
                JSON_OBJECT(
                    'rate',      CAST(tax_rate AS DECIMAL(5,2)),
                    'baseHt',    total_ht,
                    'taxAmount', tax_amount
                )
            )
            WHERE tax_amount > 0 OR total_ht > 0
        SQL);

        $this->addSql('ALTER TABLE invoice DROP COLUMN tax_rate');

        // Taux par défaut global (remplace le 20% codé en dur)
        $this->addSql("INSERT INTO app_setting (setting_key, value) VALUES ('invoice.default_tax_rate', '20') ON DUPLICATE KEY UPDATE value = value");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_category DROP COLUMN tax_rate');
        $this->addSql('ALTER TABLE invoice ADD tax_rate INT NOT NULL DEFAULT 20');
        $this->addSql('ALTER TABLE invoice DROP COLUMN tax_breakdown');
        $this->addSql("DELETE FROM app_setting WHERE setting_key = 'invoice.default_tax_rate'");
    }
}
