<?php

declare(strict_types=1);

namespace Migrations\MySql;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version30500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE file ADD is_vectorized TINYINT(1) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE library_item ADD external_id VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_B9D4EF739F75D7B0 ON library_item (external_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE file DROP is_vectorized
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_B9D4EF739F75D7B0 ON library_item
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE library_item DROP external_id
        SQL);
    }
}
