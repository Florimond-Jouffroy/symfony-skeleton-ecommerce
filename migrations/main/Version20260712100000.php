<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Florimond\MultiDbMigrationsBundle\Migrations\CustomMigration;

final class Version20260712100000 extends CustomMigration
{
    protected ?string $targetDatabase = 'main';

    public function getDescription(): string
    {
        return 'Add customer, order, order_item, order_status_history tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE customer (
            id INT AUTO_INCREMENT NOT NULL,
            email VARCHAR(180) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            UNIQUE INDEX UNIQ_customer_email (email),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE `order` (
            id INT AUTO_INCREMENT NOT NULL,
            customer_id INT NOT NULL,
            order_number VARCHAR(30) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            subtotal INT NOT NULL DEFAULT 0,
            discount_amount INT NOT NULL DEFAULT 0,
            shipping_amount INT NOT NULL DEFAULT 0,
            total INT NOT NULL DEFAULT 0,
            shipping_address JSON NOT NULL,
            billing_address JSON DEFAULT NULL,
            promo_code VARCHAR(50) DEFAULT NULL,
            customer_note TEXT DEFAULT NULL,
            internal_note TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            UNIQUE INDEX UNIQ_order_number (order_number),
            INDEX IDX_order_customer (customer_id),
            INDEX IDX_order_status (status),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql('ALTER TABLE `order`
            ADD CONSTRAINT FK_order_customer FOREIGN KEY (customer_id) REFERENCES customer (id)');

        $this->addSql("CREATE TABLE order_item (
            id INT AUTO_INCREMENT NOT NULL,
            order_id INT NOT NULL,
            product_id INT DEFAULT NULL,
            variant_id INT DEFAULT NULL,
            product_name VARCHAR(255) NOT NULL,
            variant_name VARCHAR(255) DEFAULT NULL,
            unit_price INT NOT NULL DEFAULT 0,
            quantity INT NOT NULL DEFAULT 1,
            total INT NOT NULL DEFAULT 0,
            INDEX IDX_order_item_order (order_id),
            INDEX IDX_order_item_product (product_id),
            INDEX IDX_order_item_variant (variant_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql('ALTER TABLE order_item
            ADD CONSTRAINT FK_oi_order   FOREIGN KEY (order_id)   REFERENCES `order` (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_oi_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE SET NULL,
            ADD CONSTRAINT FK_oi_variant FOREIGN KEY (variant_id) REFERENCES product_variant (id) ON DELETE SET NULL');

        $this->addSql("CREATE TABLE order_status_history (
            id INT AUTO_INCREMENT NOT NULL,
            order_id INT NOT NULL,
            status VARCHAR(20) NOT NULL,
            comment VARCHAR(500) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            INDEX IDX_osh_order (order_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql('ALTER TABLE order_status_history
            ADD CONSTRAINT FK_osh_order FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_status_history DROP FOREIGN KEY FK_osh_order');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_oi_order');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_oi_product');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_oi_variant');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_order_customer');
        $this->addSql('DROP TABLE order_status_history');
        $this->addSql('DROP TABLE order_item');
        $this->addSql('DROP TABLE `order`');
        $this->addSql('DROP TABLE customer');
    }
}
