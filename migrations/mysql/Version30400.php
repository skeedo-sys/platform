<?php

declare(strict_types=1);

namespace Migrations\MySql;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version30400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE message ADD speech_file_id BINARY(16) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE message ADD CONSTRAINT FK_B6BD307F33F96908 FOREIGN KEY (speech_file_id) REFERENCES file (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_B6BD307F33F96908 ON message (speech_file_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F33F96908
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_B6BD307F33F96908 ON message
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE message DROP speech_file_id
        SQL);
    }
}
