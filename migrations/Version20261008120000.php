<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * "Reserve Your Design" address in separate fields (design merge, 8 October
 * 2026): address lines, town, county, postcode and country on requests. The
 * old single address box stays for requests already received (now
 * optional). Written by hand so it contains only these changes.
 */
final class Version20261008120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Request address in separate fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE design_request ADD address_line1 VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE design_request ADD address_line2 VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE design_request ADD town VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE design_request ADD county VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE design_request ADD postcode VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE design_request ADD country VARCHAR(2) DEFAULT NULL');
        $this->addSql('ALTER TABLE design_request ALTER delivery_address DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Requests made with the separate fields get their address back as one block of text.
        $this->addSql("UPDATE design_request SET delivery_address = concat_ws(E'\\n', address_line1, nullif(address_line2, ''), town, nullif(county, ''), postcode) WHERE delivery_address IS NULL");
        $this->addSql('ALTER TABLE design_request ALTER delivery_address SET NOT NULL');
        $this->addSql('ALTER TABLE design_request DROP address_line1');
        $this->addSql('ALTER TABLE design_request DROP address_line2');
        $this->addSql('ALTER TABLE design_request DROP town');
        $this->addSql('ALTER TABLE design_request DROP county');
        $this->addSql('ALTER TABLE design_request DROP postcode');
        $this->addSql('ALTER TABLE design_request DROP country');
    }
}
