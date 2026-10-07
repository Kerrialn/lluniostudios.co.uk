<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * "Reserve Your Design" requests (design merge, Step 8):
 * - design_request: the customer's details, request number and status
 *   (new / quoted / paid)
 * - design_request_item: each requested configuration, snapshotted like an
 *   order line (size, quantity, price at the time, options per lamp)
 * Written by hand so it contains only these changes.
 */
final class Version20261007120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reserve Your Design requests';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE design_request (id UUID NOT NULL, request_number VARCHAR(32) NOT NULL, status VARCHAR(16) NOT NULL, full_name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(32) NOT NULL, delivery_address TEXT NOT NULL, access_notes TEXT DEFAULT NULL, custom_details TEXT DEFAULT NULL, created_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A23C1FB5DDC9DCAF ON design_request (request_number)');
        $this->addSql("COMMENT ON COLUMN design_request.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN design_request.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql(<<<'SQL'
            CREATE TABLE design_request_item (id UUID NOT NULL, design_request_id UUID NOT NULL, product_id UUID DEFAULT NULL, product_title VARCHAR(255) NOT NULL, size_name VARCHAR(64) NOT NULL, unit_price INT NOT NULL, quantity INT NOT NULL, customised_individually BOOLEAN NOT NULL, position INT NOT NULL, options_snapshot JSON NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql('CREATE INDEX IDX_2323573747E18B70 ON design_request_item (design_request_id)');
        $this->addSql('CREATE INDEX IDX_232357374584665A ON design_request_item (product_id)');
        $this->addSql("COMMENT ON COLUMN design_request_item.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN design_request_item.design_request_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN design_request_item.product_id IS '(DC2Type:uuid)'");
        $this->addSql('ALTER TABLE design_request_item ADD CONSTRAINT FK_2323573747E18B70 FOREIGN KEY (design_request_id) REFERENCES design_request (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE design_request_item ADD CONSTRAINT FK_232357374584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE design_request_item DROP CONSTRAINT FK_2323573747E18B70');
        $this->addSql('ALTER TABLE design_request_item DROP CONSTRAINT FK_232357374584665A');
        $this->addSql('DROP TABLE design_request_item');
        $this->addSql('DROP TABLE design_request');
    }
}
