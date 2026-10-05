<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Product data for the Lumen Forma configurator (design merge, Step 4):
 * - product: direct-order flag (checkout vs Request) and whether the weight /
 *   dimensions are confirmed packed sizes or placeholders
 * - product_option: configurator stage and display order; the option name is
 *   now unique per product instead of across all products
 * - product_option_value: goes-to-Request flag, display order, an unused price
 *   difference (pence) for later, and an optional photo
 * Written by hand so it contains only these changes.
 */
final class Version20261005120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lumen Forma product data: direct order, option stages/positions, request flags, option photos';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE product ADD direct_order BOOLEAN DEFAULT false NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product ADD packed_size_confirmed BOOLEAN DEFAULT false NOT NULL
        SQL);

        $this->addSql(<<<'SQL'
            DROP INDEX uniq_38fa41145e237e06
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_option ADD stage VARCHAR(20) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_option ADD position INT DEFAULT 0 NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_product_option_product_name ON product_option (product_id, name)
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE product_option_value ADD goes_to_request BOOLEAN DEFAULT false NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_option_value ADD position INT DEFAULT 0 NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_option_value ADD price_difference INT DEFAULT 0 NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_option_value ADD image_filename VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_option_value ADD updated_at TIMESTAMP(6) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN product_option_value.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE product_option_value DROP updated_at
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_option_value DROP image_filename
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_option_value DROP price_difference
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_option_value DROP position
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_option_value DROP goes_to_request
        SQL);

        $this->addSql(<<<'SQL'
            DROP INDEX uniq_product_option_product_name
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_option DROP position
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_option DROP stage
        SQL);
        // Restoring the old rule fails if two products now share an option name.
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_38fa41145e237e06 ON product_option (name)
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE product DROP packed_size_confirmed
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product DROP direct_order
        SQL);
    }
}
