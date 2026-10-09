<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * "View your order" (design merge, Step 11.1): each order's private link.
 * Existing orders are given one (64 random hex characters). Written by hand
 * so it contains only this change.
 */
final class Version20261009030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Orders: a private link for "View your order"';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders ADD view_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('UPDATE orders SET view_token = md5(random()::text || id::text) || md5(random()::text || clock_timestamp()::text)');
        $this->addSql('ALTER TABLE orders ALTER view_token SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E52FFDEEB0E4F8F7 ON orders (view_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_E52FFDEEB0E4F8F7');
        $this->addSql('ALTER TABLE orders DROP view_token');
    }
}
