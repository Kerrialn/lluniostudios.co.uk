<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Card Payment page (design merge, Step 10.2): the details it collects that
 * had nowhere to go: a company name on addresses, and on orders the billing
 * address (when not the delivery address), the contact phone and the
 * delivery instructions. Existing rows get empty values.
 * Written by hand so it contains only these changes.
 */
final class Version20261007190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Card Payment page details: address company; order billing address, contact phone, delivery notes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE address ADD company VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD billing_address_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD contact_phone VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD delivery_notes VARCHAR(64) DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN orders.billing_address_id IS '(DC2Type:uuid)'");
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE79D0C0E4 FOREIGN KEY (billing_address_id) REFERENCES address (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_E52FFDEE79D0C0E4 ON orders (billing_address_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT FK_E52FFDEE79D0C0E4');
        $this->addSql('DROP INDEX IDX_E52FFDEE79D0C0E4');
        $this->addSql('ALTER TABLE orders DROP billing_address_id');
        $this->addSql('ALTER TABLE orders DROP contact_phone');
        $this->addSql('ALTER TABLE orders DROP delivery_notes');
        $this->addSql('ALTER TABLE address DROP company');
    }
}
