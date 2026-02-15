<?php

declare(strict_types=1);

namespace Migrations\MySql;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version30800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE import_job (status VARCHAR(255) NOT NULL, processed_count INT DEFAULT 0 NOT NULL, total_count INT DEFAULT NULL, skipped_count INT DEFAULT 0 NOT NULL, failed_count INT DEFAULT 0 NOT NULL, error_message LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, started_at DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL, id BINARY(16) NOT NULL, source VARCHAR(255) DEFAULT NULL, workspace_id BINARY(16) NOT NULL, user_id BINARY(16) NOT NULL, file_id BINARY(16) NOT NULL, INDEX IDX_6FB5407882D40A1F (workspace_id), INDEX IDX_6FB54078A76ED395 (user_id), INDEX IDX_6FB5407893CB796C (file_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_job ADD CONSTRAINT FK_6FB5407882D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_job ADD CONSTRAINT FK_6FB54078A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_job ADD CONSTRAINT FK_6FB5407893CB796C FOREIGN KEY (file_id) REFERENCES file (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE file CHANGE url url LONGTEXT DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE import_job DROP FOREIGN KEY FK_6FB5407882D40A1F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_job DROP FOREIGN KEY FK_6FB54078A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_job DROP FOREIGN KEY FK_6FB5407893CB796C
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE import_job
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE file CHANGE url url LONGTEXT NOT NULL
        SQL);
    }
}
