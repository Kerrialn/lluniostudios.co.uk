<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Per-lamp basket configurations (design merge, Step 6, "Customise Individually"):
 * - cart_item.customised_individually: each lamp on the line has its own options
 * - cart_item_option.lamp: 0 = every lamp on the line, 1..n = that lamp
 * - uniqueness becomes one value per option per lamp (two lamps on one line
 *   may now choose the same value)
 * Written by hand so it contains only these changes.
 */
final class Version20261006120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Per-lamp basket configurations (customise individually)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE cart_item ADD customised_individually BOOLEAN DEFAULT false NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX cart_item_optval_unique
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cart_item_option ADD lamp INT DEFAULT 0 NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX cart_item_option_lamp_unique ON cart_item_option (cart_item_id, product_option_id, lamp)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP INDEX cart_item_option_lamp_unique
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cart_item_option DROP lamp
        SQL);
        // Fails if a line has two lamps sharing a value; remove those first.
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX cart_item_optval_unique ON cart_item_option (cart_item_id, product_option_value_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cart_item DROP customised_individually
        SQL);
    }
}
