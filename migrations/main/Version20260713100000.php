<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add invoice and app_setting tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE invoice (
                id              INT AUTO_INCREMENT NOT NULL,
                order_id        INT NOT NULL,
                invoice_number  VARCHAR(30) NOT NULL,
                status          VARCHAR(20) NOT NULL DEFAULT 'pending',
                issued_at       DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                subtotal_ht     INT NOT NULL DEFAULT 0,
                shipping_ht     INT NOT NULL DEFAULT 0,
                discount_amount INT NOT NULL DEFAULT 0,
                total_ht        INT NOT NULL DEFAULT 0,
                tax_rate        INT NOT NULL DEFAULT 20,
                tax_amount      INT NOT NULL DEFAULT 0,
                total_ttc       INT NOT NULL DEFAULT 0,
                billing_address JSON NOT NULL,
                created_at      DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at      DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX UNIQ_906517442DA68207 (invoice_number),
                UNIQUE INDEX UNIQ_906517448D9F6D38 (order_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE invoice
                ADD CONSTRAINT FK_906517448D9F6D38
                FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE app_setting (
                setting_key  VARCHAR(100) NOT NULL,
                value        LONGTEXT NOT NULL,
                PRIMARY KEY(setting_key)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        // Default: auto-generate invoice when order is created
        $this->addSql("INSERT INTO app_setting (setting_key, value) VALUES ('invoice.trigger', 'on_order')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_906517448D9F6D38');
        $this->addSql('DROP TABLE invoice');
        $this->addSql('DROP TABLE app_setting');
    }
}
