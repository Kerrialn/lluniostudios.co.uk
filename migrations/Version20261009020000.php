<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * "Deposit Paid" (design merge, Step 10b.4): orders paid by deposit with the
 * balance still owed, and their requests, move from Paid to Deposit Paid.
 * Data only (the status columns are plain text). Written by hand.
 */
final class Version20261009020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Orders and requests paid by deposit: status Deposit Paid';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE orders SET status = 'deposit_paid' WHERE status = 'paid' AND amount_paid < total");
        $this->addSql("UPDATE design_request SET status = 'deposit_paid' WHERE status = 'paid' AND id IN (SELECT design_request_id FROM orders WHERE status = 'deposit_paid')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE design_request SET status = 'paid' WHERE status = 'deposit_paid'");
        $this->addSql("UPDATE orders SET status = 'paid' WHERE status = 'deposit_paid'");
    }
}
