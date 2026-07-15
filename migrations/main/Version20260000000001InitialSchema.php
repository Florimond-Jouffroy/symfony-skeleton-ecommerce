<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Florimond\MultiDbMigrationsBundle\Migrations\CustomMigration;

final class Version20260000000001InitialSchema extends CustomMigration
{
    protected ?string $targetDatabase = 'main';

    public function getDescription(): string
    {
        return 'Initial schema — full e-commerce skeleton';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, is_verified TINYINT NOT NULL, verification_token VARCHAR(64) DEFAULT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE customer (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_81398E09E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE password_reset_token (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(6) NOT NULL, expires_at DATETIME NOT NULL, used TINYINT NOT NULL, user_id INT NOT NULL, INDEX IDX_6B7BA4B6A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_64C19C1989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE article (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, excerpt LONGTEXT DEFAULT NULL, content JSON NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, cover_image VARCHAR(500) DEFAULT NULL, published_at DATETIME DEFAULT NULL, author_id INT NOT NULL, UNIQUE INDEX UNIQ_23A0E66989D9B62 (slug), INDEX IDX_23A0E66F675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE article_category (article_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_53A4EDAA7294869C (article_id), INDEX IDX_53A4EDAA12469DE2 (category_id), PRIMARY KEY(article_id, category_id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE media_file (id INT AUTO_INCREMENT NOT NULL, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(50) NOT NULL, size INT NOT NULL, hash VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, uploaded_by_id INT NOT NULL, UNIQUE INDEX UNIQ_4FD8E9C3D1B862B8 (hash), INDEX IDX_4FD8E9C3A2B28FE8 (uploaded_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE faq_item (id INT AUTO_INCREMENT NOT NULL, question LONGTEXT NOT NULL, answer LONGTEXT NOT NULL, position INT NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE static_page (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, content JSON NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_8FA4EF95989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE product_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(100) NOT NULL, position INT DEFAULT 0 NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, tax_rate INT DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_CDFC7356989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description JSON DEFAULT NULL, price INT NOT NULL, compare_at_price INT DEFAULT NULL, status VARCHAR(20) DEFAULT 'draft' NOT NULL, stock INT DEFAULT 0 NOT NULL, low_stock_threshold INT DEFAULT 5 NOT NULL, has_variants TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_D34A04AD989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE product_product_category (product_id INT NOT NULL, product_category_id INT NOT NULL, INDEX IDX_437017AA4584665A (product_id), INDEX IDX_437017AABE6903FD (product_category_id), PRIMARY KEY(product_id, product_category_id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE product_image (id INT AUTO_INCREMENT NOT NULL, url VARCHAR(500) NOT NULL, alt VARCHAR(255) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, product_id INT NOT NULL, INDEX IDX_64617F034584665A (product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE product_variant (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, sku VARCHAR(100) DEFAULT NULL, price_override INT DEFAULT NULL, stock INT DEFAULT 0 NOT NULL, low_stock_threshold INT DEFAULT 5 NOT NULL, position INT DEFAULT 0 NOT NULL, attributes JSON DEFAULT NULL, is_active TINYINT DEFAULT 1 NOT NULL, product_id INT NOT NULL, UNIQUE INDEX UNIQ_209AA41DF9038C4 (sku), INDEX IDX_209AA41D4584665A (product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE product_review (id INT AUTO_INCREMENT NOT NULL, author_name VARCHAR(180) NOT NULL, rating SMALLINT NOT NULL, comment LONGTEXT DEFAULT NULL, is_approved TINYINT NOT NULL, created_at DATETIME NOT NULL, product_id INT NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_1B3FC0624584665A (product_id), INDEX IDX_1B3FC062A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE shipping_method (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, price INT DEFAULT 0 NOT NULL, free_above_amount INT DEFAULT NULL, is_active TINYINT DEFAULT 1 NOT NULL, position INT DEFAULT 0 NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE promo_code (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, type VARCHAR(10) NOT NULL, value INT NOT NULL, expires_at DATETIME DEFAULT NULL, max_uses INT DEFAULT NULL, used_count INT DEFAULT 0 NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_3D8C939E77153098 (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE `order` (id INT AUTO_INCREMENT NOT NULL, order_number VARCHAR(30) NOT NULL, status VARCHAR(20) DEFAULT 'pending' NOT NULL, subtotal INT NOT NULL, discount_amount INT DEFAULT 0 NOT NULL, shipping_amount INT DEFAULT 0 NOT NULL, total INT NOT NULL, shipping_address JSON NOT NULL, billing_address JSON DEFAULT NULL, promo_code VARCHAR(50) DEFAULT NULL, customer_note LONGTEXT DEFAULT NULL, internal_note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, customer_id INT NOT NULL, UNIQUE INDEX UNIQ_F5299398551F0F81 (order_number), INDEX IDX_F52993989395C3F3 (customer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE order_item (id INT AUTO_INCREMENT NOT NULL, product_name VARCHAR(255) NOT NULL, variant_name VARCHAR(255) DEFAULT NULL, unit_price INT NOT NULL, quantity INT NOT NULL, total INT NOT NULL, order_id INT NOT NULL, product_id INT DEFAULT NULL, variant_id INT DEFAULT NULL, INDEX IDX_52EA1F098D9F6D38 (order_id), INDEX IDX_52EA1F094584665A (product_id), INDEX IDX_52EA1F093B69A9AF (variant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE order_status_history (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, comment VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, order_id INT NOT NULL, INDEX IDX_471AD77E8D9F6D38 (order_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE invoice (id INT AUTO_INCREMENT NOT NULL, invoice_number VARCHAR(30) NOT NULL, status VARCHAR(20) DEFAULT 'pending' NOT NULL, issued_at DATETIME NOT NULL, subtotal_ht INT NOT NULL, shipping_ht INT NOT NULL, discount_amount INT DEFAULT 0 NOT NULL, total_ht INT NOT NULL, tax_breakdown JSON NOT NULL, tax_amount INT NOT NULL, total_ttc INT NOT NULL, billing_address JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, order_id INT NOT NULL, UNIQUE INDEX UNIQ_906517442DA68207 (invoice_number), UNIQUE INDEX UNIQ_906517448D9F6D38 (order_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE support_ticket (id INT AUTO_INCREMENT NOT NULL, subject VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, guest_name VARCHAR(100) DEFAULT NULL, guest_email VARCHAR(180) DEFAULT NULL, token VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_1F5A4D535F37A13B (token), INDEX IDX_1F5A4D53A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE support_message (id INT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, is_from_admin TINYINT NOT NULL, author_name VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, ticket_id INT NOT NULL, INDEX IDX_B883883700047D2 (ticket_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE activity_log (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(100) NOT NULL, entity_type VARCHAR(50) NOT NULL, entity_id INT DEFAULT NULL, entity_label VARCHAR(255) NOT NULL, context JSON NOT NULL, performed_by_email VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_entity_type (entity_type), INDEX IDX_created_at (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE app_setting (setting_key VARCHAR(100) NOT NULL, value LONGTEXT NOT NULL, PRIMARY KEY(setting_key)) DEFAULT CHARACTER SET utf8mb4");

        // Foreign keys
        $this->addSql('ALTER TABLE password_reset_token ADD CONSTRAINT FK_6B7BA4B6A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66F675F31B FOREIGN KEY (author_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE article_category ADD CONSTRAINT FK_53A4EDAA7294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_category ADD CONSTRAINT FK_53A4EDAA12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE media_file ADD CONSTRAINT FK_4FD8E9C3A2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE product_product_category ADD CONSTRAINT FK_437017AA4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_product_category ADD CONSTRAINT FK_437017AABE6903FD FOREIGN KEY (product_category_id) REFERENCES product_category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_image ADD CONSTRAINT FK_64617F034584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_variant ADD CONSTRAINT FK_209AA41D4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_review ADD CONSTRAINT FK_1B3FC0624584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_review ADD CONSTRAINT FK_1B3FC062A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F52993989395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id)');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F098D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F094584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F093B69A9AF FOREIGN KEY (variant_id) REFERENCES product_variant (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE order_status_history ADD CONSTRAINT FK_471AD77E8D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_906517448D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE support_ticket ADD CONSTRAINT FK_1F5A4D53A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE support_message ADD CONSTRAINT FK_B883883700047D2 FOREIGN KEY (ticket_id) REFERENCES support_ticket (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE password_reset_token DROP FOREIGN KEY FK_6B7BA4B6A76ED395');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E66F675F31B');
        $this->addSql('ALTER TABLE article_category DROP FOREIGN KEY FK_53A4EDAA7294869C');
        $this->addSql('ALTER TABLE article_category DROP FOREIGN KEY FK_53A4EDAA12469DE2');
        $this->addSql('ALTER TABLE media_file DROP FOREIGN KEY FK_4FD8E9C3A2B28FE8');
        $this->addSql('ALTER TABLE product_product_category DROP FOREIGN KEY FK_437017AA4584665A');
        $this->addSql('ALTER TABLE product_product_category DROP FOREIGN KEY FK_437017AABE6903FD');
        $this->addSql('ALTER TABLE product_image DROP FOREIGN KEY FK_64617F034584665A');
        $this->addSql('ALTER TABLE product_variant DROP FOREIGN KEY FK_209AA41D4584665A');
        $this->addSql('ALTER TABLE product_review DROP FOREIGN KEY FK_1B3FC0624584665A');
        $this->addSql('ALTER TABLE product_review DROP FOREIGN KEY FK_1B3FC062A76ED395');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F52993989395C3F3');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F098D9F6D38');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F094584665A');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F093B69A9AF');
        $this->addSql('ALTER TABLE order_status_history DROP FOREIGN KEY FK_471AD77E8D9F6D38');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_906517448D9F6D38');
        $this->addSql('ALTER TABLE support_ticket DROP FOREIGN KEY FK_1F5A4D53A76ED395');
        $this->addSql('ALTER TABLE support_message DROP FOREIGN KEY FK_B883883700047D2');

        $this->addSql('DROP TABLE IF EXISTS support_message');
        $this->addSql('DROP TABLE IF EXISTS support_ticket');
        $this->addSql('DROP TABLE IF EXISTS invoice');
        $this->addSql('DROP TABLE IF EXISTS order_status_history');
        $this->addSql('DROP TABLE IF EXISTS order_item');
        $this->addSql('DROP TABLE IF EXISTS `order`');
        $this->addSql('DROP TABLE IF EXISTS product_review');
        $this->addSql('DROP TABLE IF EXISTS product_variant');
        $this->addSql('DROP TABLE IF EXISTS product_image');
        $this->addSql('DROP TABLE IF EXISTS product_product_category');
        $this->addSql('DROP TABLE IF EXISTS product');
        $this->addSql('DROP TABLE IF EXISTS promo_code');
        $this->addSql('DROP TABLE IF EXISTS shipping_method');
        $this->addSql('DROP TABLE IF EXISTS product_category');
        $this->addSql('DROP TABLE IF EXISTS static_page');
        $this->addSql('DROP TABLE IF EXISTS faq_item');
        $this->addSql('DROP TABLE IF EXISTS media_file');
        $this->addSql('DROP TABLE IF EXISTS article_category');
        $this->addSql('DROP TABLE IF EXISTS article');
        $this->addSql('DROP TABLE IF EXISTS category');
        $this->addSql('DROP TABLE IF EXISTS password_reset_token');
        $this->addSql('DROP TABLE IF EXISTS activity_log');
        $this->addSql('DROP TABLE IF EXISTS app_setting');
        $this->addSql('DROP TABLE IF EXISTS customer');
        $this->addSql('DROP TABLE IF EXISTS user');
    }
}
