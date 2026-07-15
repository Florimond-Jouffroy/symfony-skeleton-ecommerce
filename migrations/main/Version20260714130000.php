<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260714130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table product_review';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE product_review (
                id          INT AUTO_INCREMENT NOT NULL,
                product_id  INT NOT NULL,
                user_id     INT DEFAULT NULL,
                author_name VARCHAR(180) NOT NULL,
                rating      SMALLINT NOT NULL,
                comment     LONGTEXT DEFAULT NULL,
                is_approved TINYINT(1) NOT NULL DEFAULT 0,
                created_at  DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX IDX_PRODUCT_REVIEW_PRODUCT (product_id),
                INDEX IDX_PRODUCT_REVIEW_USER (user_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');
        $this->addSql('ALTER TABLE product_review ADD CONSTRAINT FK_PR_PRODUCT FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_review ADD CONSTRAINT FK_PR_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_review DROP FOREIGN KEY FK_PR_PRODUCT');
        $this->addSql('ALTER TABLE product_review DROP FOREIGN KEY FK_PR_USER');
        $this->addSql('DROP TABLE product_review');
    }
}
