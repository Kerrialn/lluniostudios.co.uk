<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Dispatched (design merge, Step 11.3): when an order was dispatched, the
 * courier and the tracking number. Written by hand so it contains only
 * these changes.
 */
final class Version20261009060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Orders: dispatched, courier and tracking number';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders ADD dispatched_at TIMESTAMP(6) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD courier VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD tracking_number VARCHAR(64) DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN orders.dispatched_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE orders SET stage = 'in_production' WHERE stage = 'dispatched'");
        $this->addSql('ALTER TABLE orders DROP dispatched_at');
        $this->addSql('ALTER TABLE orders DROP courier');
        $this->addSql('ALTER TABLE orders DROP tracking_number');
    }
}
