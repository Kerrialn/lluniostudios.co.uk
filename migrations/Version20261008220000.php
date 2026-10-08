<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Paying quotes (design merge, Step 10b.3): an order can belong to a quoted
 * request, take a deposit (charge_now) and record what has been paid
 * (amount_paid; orders already paid count as paid in full). Written by hand
 * so it contains only these changes.
 */
final class Version20261008220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Orders from quotes: the request, the amount charged now and the amount paid';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders ADD design_request_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD charge_now INT DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD amount_paid INT DEFAULT 0 NOT NULL');
        $this->addSql("COMMENT ON COLUMN orders.design_request_id IS '(DC2Type:uuid)'");
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE47E18B70 FOREIGN KEY (design_request_id) REFERENCES design_request (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_E52FFDEE47E18B70 ON orders (design_request_id)');
        $this->addSql("UPDATE orders SET amount_paid = total WHERE status IN ('paid', 'fulfilled')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT FK_E52FFDEE47E18B70');
        $this->addSql('DROP INDEX IDX_E52FFDEE47E18B70');
        $this->addSql('ALTER TABLE orders DROP design_request_id');
        $this->addSql('ALTER TABLE orders DROP charge_now');
        $this->addSql('ALTER TABLE orders DROP amount_paid');
    }
}
