<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Requesting the balance (design merge, Step 10b.4): when the balance was
 * asked for, its own Stripe payment and when it was paid. Written by hand so
 * it contains only these changes.
 */
final class Version20261009010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Orders: balance requested, its payment and when it was paid';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders ADD balance_requested_at TIMESTAMP(6) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD balance_payment_intent_id VARCHAR(128) DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD balance_paid_at TIMESTAMP(6) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN orders.balance_requested_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN orders.balance_paid_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders DROP balance_requested_at');
        $this->addSql('ALTER TABLE orders DROP balance_payment_intent_id');
        $this->addSql('ALTER TABLE orders DROP balance_paid_at');
    }
}
