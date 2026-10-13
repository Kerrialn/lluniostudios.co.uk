<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * "Begin the Conversation" enquiries saved as requests (Step 7c.2):
 * - design_request.type: reservation (every request so far) or enquiry
 * - an enquiry's own details: how they'd like to talk, the best time, their
 *   space and what they have in mind, the board link
 * - the phone may be empty (optional on an enquiry unless they chose a phone
 *   or video call)
 * Undo (doctrine:migrations:migrate prev) removes these columns, so saved
 * enquiries become plain requests without those details, and an empty phone
 * becomes blank. Their size, quantity, postcode and files are kept.
 * Written by hand so it contains only these changes.
 */
final class Version20261013100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enquiries saved as requests';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE design_request ADD type VARCHAR(16) DEFAULT 'reservation' NOT NULL");
        $this->addSql('ALTER TABLE design_request ADD talk VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE design_request ADD best_time VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE design_request ADD description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE design_request ADD board_link VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE design_request ALTER phone DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE design_request SET phone = '' WHERE phone IS NULL");
        $this->addSql('ALTER TABLE design_request ALTER phone SET NOT NULL');
        $this->addSql('ALTER TABLE design_request DROP board_link');
        $this->addSql('ALTER TABLE design_request DROP description');
        $this->addSql('ALTER TABLE design_request DROP best_time');
        $this->addSql('ALTER TABLE design_request DROP talk');
        $this->addSql('ALTER TABLE design_request DROP type');
    }
}
