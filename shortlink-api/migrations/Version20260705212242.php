<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260705212242 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE short_link (id INT AUTO_INCREMENT NOT NULL, original_url LONGTEXT NOT NULL, original_url_hash VARCHAR(64) NOT NULL, short_code VARCHAR(8) DEFAULT NULL, status VARCHAR(20) NOT NULL, generation_attempts INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_566B5764ECB34C11 (original_url_hash), UNIQUE INDEX UNIQ_566B576417D2FE0D (short_code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE short_link');
    }
}
