<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Gift options and greeting names (design merge, Step 11.1b): a gift's
 * "Send to me" / "Send directly to the recipient" on baskets, orders and
 * requests; a direct gift's message and recipient's mobile (and, on a
 * request, the recipient's name); a request's gift and "Needed by" date; an
 * order's buyer name. Written by hand so it contains only these changes.
 */
final class Version20261009040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Gift options on baskets, orders and requests; the buyer\'s name on orders';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cart ADD gift_send_to VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD gift_send_to VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD gift_message VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD gift_recipient_phone VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD customer_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE design_request ADD gift BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE design_request ADD gift_needed_by DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE design_request ADD gift_send_to VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE design_request ADD gift_recipient_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE design_request ADD gift_message VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE design_request ADD gift_recipient_phone VARCHAR(32) DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN design_request.gift_needed_by IS '(DC2Type:date_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cart DROP gift_send_to');
        $this->addSql('ALTER TABLE orders DROP gift_send_to');
        $this->addSql('ALTER TABLE orders DROP gift_message');
        $this->addSql('ALTER TABLE orders DROP gift_recipient_phone');
        $this->addSql('ALTER TABLE orders DROP customer_name');
        $this->addSql('ALTER TABLE design_request DROP gift');
        $this->addSql('ALTER TABLE design_request DROP gift_needed_by');
        $this->addSql('ALTER TABLE design_request DROP gift_send_to');
        $this->addSql('ALTER TABLE design_request DROP gift_recipient_name');
        $this->addSql('ALTER TABLE design_request DROP gift_message');
        $this->addSql('ALTER TABLE design_request DROP gift_recipient_phone');
    }
}
