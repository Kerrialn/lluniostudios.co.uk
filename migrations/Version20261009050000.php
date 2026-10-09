<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * In Production (design merge, Step 11.2): an order's stage, when it was
 * marked and the expected dispatch date. Written by hand so it contains
 * only these changes.
 */
final class Version20261009050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Orders: stage (In Production), when marked, expected dispatch';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders ADD stage VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD in_production_at TIMESTAMP(6) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD expected_dispatch DATE DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN orders.in_production_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN orders.expected_dispatch IS '(DC2Type:date_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders DROP stage');
        $this->addSql('ALTER TABLE orders DROP in_production_at');
        $this->addSql('ALTER TABLE orders DROP expected_dispatch');
    }
}
