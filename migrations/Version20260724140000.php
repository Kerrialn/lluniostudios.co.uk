<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drop the users.password column — authentication is now passwordless (email codes).
 */
final class Version20260724140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop users.password (passwordless email code auth)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE users DROP password
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE users ADD password VARCHAR(255) DEFAULT NULL
        SQL);
    }
}
