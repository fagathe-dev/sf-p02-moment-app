<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260629050010 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, color VARCHAR(20) DEFAULT NULL, slug VARCHAR(120) DEFAULT NULL, owner_id INT DEFAULT NULL, INDEX IDX_64C19C17E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE entry (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT DEFAULT NULL, title VARCHAR(200) DEFAULT NULL, color VARCHAR(30) DEFAULT NULL, slug VARCHAR(255) DEFAULT NULL, is_private TINYINT NOT NULL, mood VARCHAR(50) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, owner_id INT DEFAULT NULL, location_id INT DEFAULT NULL, INDEX IDX_2B219D707E3C61F9 (owner_id), INDEX IDX_2B219D7064D218E (location_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE entry_category (entry_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_680BF989BA364942 (entry_id), INDEX IDX_680BF98912469DE2 (category_id), PRIMARY KEY (entry_id, category_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE entry_media (id INT AUTO_INCREMENT NOT NULL, original_name VARCHAR(255) NOT NULL, nice_name VARCHAR(255) DEFAULT NULL, file_path VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, type VARCHAR(30) DEFAULT NULL, extension VARCHAR(10) NOT NULL, size INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, is_pinned TINYINT DEFAULT 0 NOT NULL, owner_id INT DEFAULT NULL, entry_id INT DEFAULT NULL, INDEX IDX_EE36C2AA7E3C61F9 (owner_id), INDEX IDX_EE36C2AABA364942 (entry_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE location (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, street VARCHAR(255) DEFAULT NULL, city VARCHAR(255) DEFAULT NULL, country VARCHAR(255) DEFAULT NULL, owner_id INT NOT NULL, INDEX IDX_5E9E89CB7E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, username VARCHAR(80) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, avatar VARCHAR(255) DEFAULT NULL, is_verified TINYINT DEFAULT NULL, verified_at DATETIME DEFAULT NULL, preferences JSON DEFAULT NULL, private_secret VARCHAR(255) DEFAULT NULL, vault_token_session VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_request (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, token VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, expires_at DATETIME DEFAULT NULL, used_at DATETIME DEFAULT NULL, is_used TINYINT DEFAULT NULL, content JSON DEFAULT NULL, user_id INT DEFAULT NULL, INDEX IDX_639A9195A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C17E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE entry ADD CONSTRAINT FK_2B219D707E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE entry ADD CONSTRAINT FK_2B219D7064D218E FOREIGN KEY (location_id) REFERENCES location (id)');
        $this->addSql('ALTER TABLE entry_category ADD CONSTRAINT FK_680BF989BA364942 FOREIGN KEY (entry_id) REFERENCES entry (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE entry_category ADD CONSTRAINT FK_680BF98912469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE entry_media ADD CONSTRAINT FK_EE36C2AA7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE entry_media ADD CONSTRAINT FK_EE36C2AABA364942 FOREIGN KEY (entry_id) REFERENCES entry (id)');
        $this->addSql('ALTER TABLE location ADD CONSTRAINT FK_5E9E89CB7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_request ADD CONSTRAINT FK_639A9195A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C17E3C61F9');
        $this->addSql('ALTER TABLE entry DROP FOREIGN KEY FK_2B219D707E3C61F9');
        $this->addSql('ALTER TABLE entry DROP FOREIGN KEY FK_2B219D7064D218E');
        $this->addSql('ALTER TABLE entry_category DROP FOREIGN KEY FK_680BF989BA364942');
        $this->addSql('ALTER TABLE entry_category DROP FOREIGN KEY FK_680BF98912469DE2');
        $this->addSql('ALTER TABLE entry_media DROP FOREIGN KEY FK_EE36C2AA7E3C61F9');
        $this->addSql('ALTER TABLE entry_media DROP FOREIGN KEY FK_EE36C2AABA364942');
        $this->addSql('ALTER TABLE location DROP FOREIGN KEY FK_5E9E89CB7E3C61F9');
        $this->addSql('ALTER TABLE user_request DROP FOREIGN KEY FK_639A9195A76ED395');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE entry');
        $this->addSql('DROP TABLE entry_category');
        $this->addSql('DROP TABLE entry_media');
        $this->addSql('DROP TABLE location');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_request');
    }
}
