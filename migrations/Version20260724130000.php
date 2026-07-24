<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create login_code table for passwordless 6-digit email code authentication.
 */
final class Version20260724130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create login_code table for email code authentication';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE login_code (id UUID NOT NULL, user_id UUID NOT NULL, code_hash VARCHAR(255) NOT NULL, expires_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL, attempts INT NOT NULL, consumed_at TIMESTAMP(6) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_LOGIN_CODE_USER ON login_code (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN login_code.id IS '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN login_code.user_id IS '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN login_code.expires_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN login_code.consumed_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN login_code.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE login_code ADD CONSTRAINT FK_LOGIN_CODE_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE login_code DROP CONSTRAINT FK_LOGIN_CODE_USER
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE login_code
        SQL);
    }
}
