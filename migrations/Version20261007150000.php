<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Bespoke Commissions enquiries (design merge, Step 9.2):
 * - commission_enquiry: the customer's details from the Home or Project form,
 *   enquiry number and status (new / contacted / closed)
 * - commission_enquiry_file: each attached file's name, size and where it is
 *   kept privately (the files themselves are not in the database)
 * Written by hand so it contains only these changes.
 */
final class Version20261007150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bespoke Commissions enquiries';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE commission_enquiry (id UUID NOT NULL, enquiry_number VARCHAR(32) NOT NULL, client VARCHAR(16) NOT NULL, status VARCHAR(16) NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(32) DEFAULT NULL, contact_method VARCHAR(16) DEFAULT NULL, idea TEXT DEFAULT NULL, company VARCHAR(255) DEFAULT NULL, project_type VARCHAR(64) DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, project TEXT DEFAULT NULL, integration TEXT DEFAULT NULL, created_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_344C8F54AF3D14BC ON commission_enquiry (enquiry_number)');
        $this->addSql("COMMENT ON COLUMN commission_enquiry.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN commission_enquiry.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql(<<<'SQL'
            CREATE TABLE commission_enquiry_file (id UUID NOT NULL, enquiry_id UUID NOT NULL, original_name VARCHAR(255) NOT NULL, stored_name VARCHAR(255) NOT NULL, size BIGINT NOT NULL, position INT NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6BB90E3E1185AF6A ON commission_enquiry_file (stored_name)');
        $this->addSql('CREATE INDEX IDX_6BB90E3EBD918DFF ON commission_enquiry_file (enquiry_id)');
        $this->addSql("COMMENT ON COLUMN commission_enquiry_file.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN commission_enquiry_file.enquiry_id IS '(DC2Type:uuid)'");
        $this->addSql('ALTER TABLE commission_enquiry_file ADD CONSTRAINT FK_6BB90E3EBD918DFF FOREIGN KEY (enquiry_id) REFERENCES commission_enquiry (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commission_enquiry_file DROP CONSTRAINT FK_6BB90E3EBD918DFF');
        $this->addSql('DROP TABLE commission_enquiry_file');
        $this->addSql('DROP TABLE commission_enquiry');
    }
}
