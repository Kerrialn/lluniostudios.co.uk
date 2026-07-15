<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715121606 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE product_collection (id UUID NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, image_filename VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(6) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_6F2A3A19989D9B62 ON product_collection (slug)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN product_collection.id IS '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN product_collection.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN product_collection.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE product_collection_product (product_collection_id UUID NOT NULL, product_id UUID NOT NULL, PRIMARY KEY(product_collection_id, product_id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_75B0F3008BA44A0F ON product_collection_product (product_collection_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_75B0F3004584665A ON product_collection_product (product_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN product_collection_product.product_collection_id IS '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN product_collection_product.product_id IS '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_collection_product ADD CONSTRAINT FK_75B0F3008BA44A0F FOREIGN KEY (product_collection_id) REFERENCES product_collection (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_collection_product ADD CONSTRAINT FK_75B0F3004584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_collection_product DROP CONSTRAINT FK_75B0F3008BA44A0F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_collection_product DROP CONSTRAINT FK_75B0F3004584665A
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE product_collection
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE product_collection_product
        SQL);
    }
}
