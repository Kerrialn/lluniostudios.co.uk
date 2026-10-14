<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Delivery instructions on Card Payment raised from 50 to 100 characters
 * (14 October 2026): orders.delivery_notes from 64 to 255 characters, so
 * matching DPD's real limit later (Step 12) needs no further database change.
 * Undo (doctrine:migrations:migrate prev) cuts any longer instructions to 64
 * characters, then narrows the column again.
 * Written by hand so it contains only this change.
 */
final class Version20261014100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Longer delivery instructions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders ALTER delivery_notes TYPE VARCHAR(255)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE orders SET delivery_notes = LEFT(delivery_notes, 64) WHERE LENGTH(delivery_notes) > 64');
        $this->addSql('ALTER TABLE orders ALTER delivery_notes TYPE VARCHAR(64)');
    }
}
