<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Quotes for "Reserve Your Design" requests (design merge, Step 10b.1):
 * - quote: one per request: delivery price, its description and whether it
 *   is an estimate, full payment or deposit (and its percentage), the
 *   estimated casting date and the personal note
 * - quote_line: a price per lamp design from the request, and extra named lines
 * Written by hand so it contains only these changes.
 */
final class Version20261008100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Quotes for requests';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE quote_line (id UUID NOT NULL, quote_id UUID NOT NULL, request_item_id UUID DEFAULT NULL, label VARCHAR(255) NOT NULL, quantity INT NOT NULL, unit_price INT NOT NULL, position INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_43F3EB7CDB805178 ON quote_line (quote_id)');
        $this->addSql('CREATE INDEX IDX_43F3EB7C384B2009 ON quote_line (request_item_id)');
        $this->addSql('COMMENT ON COLUMN quote_line.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN quote_line.quote_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN quote_line.request_item_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE quote (id UUID NOT NULL, design_request_id UUID NOT NULL, delivery_price INT NOT NULL, delivery_label VARCHAR(128) NOT NULL, delivery_estimated BOOLEAN NOT NULL, payment VARCHAR(16) NOT NULL, deposit_percent INT NOT NULL, casting_date DATE DEFAULT NULL, note TEXT DEFAULT NULL, created_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6B71CBF447E18B70 ON quote (design_request_id)');
        $this->addSql('COMMENT ON COLUMN quote.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN quote.design_request_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN quote.casting_date IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN quote.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN quote.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE quote_line ADD CONSTRAINT FK_43F3EB7CDB805178 FOREIGN KEY (quote_id) REFERENCES quote (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE quote_line ADD CONSTRAINT FK_43F3EB7C384B2009 FOREIGN KEY (request_item_id) REFERENCES design_request_item (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE quote ADD CONSTRAINT FK_6B71CBF447E18B70 FOREIGN KEY (design_request_id) REFERENCES design_request (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quote_line DROP CONSTRAINT FK_43F3EB7CDB805178');
        $this->addSql('ALTER TABLE quote_line DROP CONSTRAINT FK_43F3EB7C384B2009');
        $this->addSql('ALTER TABLE quote DROP CONSTRAINT FK_6B71CBF447E18B70');
        $this->addSql('DROP TABLE quote_line');
        $this->addSql('DROP TABLE quote');
    }
}
