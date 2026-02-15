<?php

declare(strict_types=1);

namespace Migrations\MySql;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version30600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE assistant ADD visibility SMALLINT NOT NULL, ADD workspace_id BINARY(16) DEFAULT NULL, ADD user_id BINARY(16) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assistant ADD CONSTRAINT FK_C2997CD182D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assistant ADD CONSTRAINT FK_C2997CD1A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C2997CD182D40A1F ON assistant (workspace_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C2997CD1A76ED395 ON assistant (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE data_unit ADD used_credit_count NUMERIC(23, 11) DEFAULT NULL
        SQL);

        // Set all current assistants' visibility to public 
        // (admin generated assistants are always public)
        $this->addSql(<<<'SQL'
            UPDATE assistant SET visibility = 2
        SQL);

        // Set all current data units' used credit count to 0
        $this->addSql(<<<'SQL'
            UPDATE data_unit SET used_credit_count = 0
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE assistant DROP FOREIGN KEY FK_C2997CD182D40A1F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assistant DROP FOREIGN KEY FK_C2997CD1A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_C2997CD182D40A1F ON assistant
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_C2997CD1A76ED395 ON assistant
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assistant DROP visibility, DROP workspace_id, DROP user_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE data_unit DROP used_credit_count
        SQL);
    }
}
