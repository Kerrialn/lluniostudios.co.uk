<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reference photos on "Reserve Your Design" requests with a Custom choice
 * (10 October 2026):
 * - design_request_file: each photo's name, size and where it is kept
 *   privately (the files themselves are not in the database; they share the
 *   Bespoke Commissions folder, var/commission-uploads)
 * Undo (doctrine:migrations:migrate prev) drops the table, so the requests
 * forget their photos (the files stay in the folder).
 * Written by hand so it contains only these changes.
 */
final class Version20261010100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reference photos on requests';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE design_request_file (id UUID NOT NULL, design_request_id UUID NOT NULL, original_name VARCHAR(255) NOT NULL, stored_name VARCHAR(255) NOT NULL, size BIGINT NOT NULL, position INT NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B0A744391185AF6A ON design_request_file (stored_name)');
        $this->addSql('CREATE INDEX IDX_B0A7443947E18B70 ON design_request_file (design_request_id)');
        $this->addSql("COMMENT ON COLUMN design_request_file.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN design_request_file.design_request_id IS '(DC2Type:uuid)'");
        $this->addSql('ALTER TABLE design_request_file ADD CONSTRAINT FK_B0A7443947E18B70 FOREIGN KEY (design_request_id) REFERENCES design_request (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE design_request_file DROP CONSTRAINT FK_B0A7443947E18B70');
        $this->addSql('DROP TABLE design_request_file');
    }
}
