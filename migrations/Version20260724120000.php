<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add stripe_payment_intent_id to orders (Stripe payment provider).
 */
final class Version20260724120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add stripe_payment_intent_id to orders';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE orders ADD stripe_payment_intent_id VARCHAR(128) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE orders DROP stripe_payment_intent_id
        SQL);
    }
}
