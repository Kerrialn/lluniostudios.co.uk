<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sending quotes (design merge, Step 10b.2): the private link's token, when
 * the quote was sent, when it expires and a copy of what was sent. Written
 * by hand so it contains only these changes.
 */
final class Version20261008200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sending quotes: token, sent and expiry times, the sent copy';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quote ADD token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE quote ADD sent_at TIMESTAMP(6) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE quote ADD expires_at TIMESTAMP(6) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE quote ADD sent JSON DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN quote.sent_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN quote.expires_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6B71CBF45F37A13B ON quote (token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_6B71CBF45F37A13B');
        $this->addSql('ALTER TABLE quote DROP token');
        $this->addSql('ALTER TABLE quote DROP sent_at');
        $this->addSql('ALTER TABLE quote DROP expires_at');
        $this->addSql('ALTER TABLE quote DROP sent');
    }
}
