<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Florimond\MultiDbMigrationsBundle\Migrations\CustomMigration;

final class Version20260711140000 extends CustomMigration
{
    protected ?string $targetDatabase = 'main';

    public function getDescription(): string
    {
        return 'Add product_category, product, product_image, product_variant tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE product_category (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            position INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            UNIQUE INDEX UNIQ_CDFC7352989D9B62 (slug),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE product (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            description JSON DEFAULT NULL,
            price INT NOT NULL DEFAULT 0,
            compare_at_price INT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            stock INT NOT NULL DEFAULT 0,
            low_stock_threshold INT NOT NULL DEFAULT 5,
            has_variants TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            UNIQUE INDEX UNIQ_D34A04AD989D9B62 (slug),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE product_product_category (
            product_id INT NOT NULL,
            product_category_id INT NOT NULL,
            INDEX IDX_product (product_id),
            INDEX IDX_product_category (product_category_id),
            PRIMARY KEY(product_id, product_category_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql('ALTER TABLE product_product_category
            ADD CONSTRAINT FK_ppc_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_ppc_category FOREIGN KEY (product_category_id) REFERENCES product_category (id) ON DELETE CASCADE');

        $this->addSql("CREATE TABLE product_image (
            id INT AUTO_INCREMENT NOT NULL,
            product_id INT NOT NULL,
            url VARCHAR(500) NOT NULL,
            alt VARCHAR(255) DEFAULT NULL,
            position INT NOT NULL DEFAULT 0,
            INDEX IDX_64617F034584665A (product_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql('ALTER TABLE product_image
            ADD CONSTRAINT FK_pi_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');

        $this->addSql("CREATE TABLE product_variant (
            id INT AUTO_INCREMENT NOT NULL,
            product_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            sku VARCHAR(100) DEFAULT NULL,
            price_override INT DEFAULT NULL,
            stock INT NOT NULL DEFAULT 0,
            low_stock_threshold INT NOT NULL DEFAULT 5,
            position INT NOT NULL DEFAULT 0,
            attributes JSON DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            UNIQUE INDEX UNIQ_product_variant_sku (sku),
            INDEX IDX_pv_product (product_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql('ALTER TABLE product_variant
            ADD CONSTRAINT FK_pv_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_variant DROP FOREIGN KEY FK_pv_product');
        $this->addSql('ALTER TABLE product_image DROP FOREIGN KEY FK_pi_product');
        $this->addSql('ALTER TABLE product_product_category DROP FOREIGN KEY FK_ppc_product');
        $this->addSql('ALTER TABLE product_product_category DROP FOREIGN KEY FK_ppc_category');
        $this->addSql('DROP TABLE product_variant');
        $this->addSql('DROP TABLE product_image');
        $this->addSql('DROP TABLE product_product_category');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE product_category');
    }
}
