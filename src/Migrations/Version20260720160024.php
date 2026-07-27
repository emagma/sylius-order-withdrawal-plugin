<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260720160024 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE emagma_order_withdrawal_request (id INT AUTO_INCREMENT NOT NULL, channel_id INT DEFAULT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, order_number VARCHAR(255) NOT NULL, order_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', not_yet_received TINYINT(1) NOT NULL, reception_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', email VARCHAR(255) NOT NULL, scope VARCHAR(255) NOT NULL, message LONGTEXT DEFAULT NULL, legal_notice_accepted TINYINT(1) NOT NULL, state VARCHAR(255) NOT NULL, submitted_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', locale_code VARCHAR(255) DEFAULT NULL, INDEX IDX_BBBAFE9472F5A1AA (channel_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE emagma_order_withdrawal_request ADD CONSTRAINT FK_BBBAFE9472F5A1AA FOREIGN KEY (channel_id) REFERENCES sylius_channel (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE emagma_order_withdrawal_request DROP FOREIGN KEY FK_BBBAFE9472F5A1AA');
        $this->addSql('DROP TABLE emagma_order_withdrawal_request');
    }
}
