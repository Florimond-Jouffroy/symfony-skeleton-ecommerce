<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Florimond\MultiDbMigrationsBundle\Migrations\CustomMigration;

/**
 * Crée les tables return_request et return_item (retours / RMA).
 */
final class Version20260718220905 extends CustomMigration
{
    protected ?string $targetDatabase = 'main';

    public function getDescription(): string
    {
        return 'Add return_request and return_item tables (RMA)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE return_item (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, return_request_id INT NOT NULL, order_item_id INT NOT NULL, INDEX IDX_7EED95F789EA1297 (return_request_id), INDEX IDX_7EED95F7E415FB15 (order_item_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE return_request (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) DEFAULT \'requested\' NOT NULL, reason LONGTEXT NOT NULL, admin_note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, order_id INT NOT NULL, customer_id INT NOT NULL, INDEX IDX_2DBF9D408D9F6D38 (order_id), INDEX IDX_2DBF9D409395C3F3 (customer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE return_item ADD CONSTRAINT FK_7EED95F789EA1297 FOREIGN KEY (return_request_id) REFERENCES return_request (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE return_item ADD CONSTRAINT FK_7EED95F7E415FB15 FOREIGN KEY (order_item_id) REFERENCES order_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE return_request ADD CONSTRAINT FK_2DBF9D408D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE return_request ADD CONSTRAINT FK_2DBF9D409395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE return_item DROP FOREIGN KEY FK_7EED95F789EA1297');
        $this->addSql('ALTER TABLE return_item DROP FOREIGN KEY FK_7EED95F7E415FB15');
        $this->addSql('ALTER TABLE return_request DROP FOREIGN KEY FK_2DBF9D408D9F6D38');
        $this->addSql('ALTER TABLE return_request DROP FOREIGN KEY FK_2DBF9D409395C3F3');
        $this->addSql('DROP TABLE return_item');
        $this->addSql('DROP TABLE return_request');
    }
}
