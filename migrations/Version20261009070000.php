<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Delivered (design merge, Step 11.4): when an order was marked delivered.
 * Written by hand so it contains only this change.
 */
final class Version20261009070000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Orders: delivered';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders ADD delivered_at TIMESTAMP(6) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN orders.delivered_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE orders SET stage = 'dispatched' WHERE stage = 'delivered'");
        $this->addSql('ALTER TABLE orders DROP delivered_at');
    }
}
