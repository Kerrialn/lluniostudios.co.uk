<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Gift option on the basket (design merge, Step 10.1): whether the order is
 * a gift and the date it must arrive by. Existing baskets are not gifts.
 * Written by hand so it contains only these changes.
 */
final class Version20261007180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Basket gift option';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cart ADD gift BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE cart ADD gift_arrive_by DATE DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN cart.gift_arrive_by IS '(DC2Type:date_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cart DROP gift');
        $this->addSql('ALTER TABLE cart DROP gift_arrive_by');
    }
}
