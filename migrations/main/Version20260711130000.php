<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Florimond\MultiDbMigrationsBundle\Migrations\CustomMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260711130000 extends CustomMigration
{
    protected ?string $targetDatabase = 'main';

    public function getDescription(): string
    {
        return 'Add category table and article_category join table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_64C19C1989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE article_category (article_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_53A4EDAA7294869C (article_id), INDEX IDX_53A4EDAA12469DE2 (category_id), PRIMARY KEY(article_id, category_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE article_category ADD CONSTRAINT FK_article FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_category ADD CONSTRAINT FK_category FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article_category DROP FOREIGN KEY FK_article');
        $this->addSql('ALTER TABLE article_category DROP FOREIGN KEY FK_category');
        $this->addSql('DROP TABLE article_category');
        $this->addSql('DROP TABLE category');
    }
}
