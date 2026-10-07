<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Gift details on orders (design merge, Step 10.5): whether the order is a
 * gift and the date it must arrive by, copied from the basket. Existing
 * orders are not gifts. Written by hand so it contains only these changes.
 */
final class Version20261007200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Order gift details';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders ADD gift BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE orders ADD gift_arrive_by DATE DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN orders.gift_arrive_by IS '(DC2Type:date_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders DROP gift');
        $this->addSql('ALTER TABLE orders DROP gift_arrive_by');
    }
}
