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
use Sylius\Bundle\CoreBundle\Doctrine\Migrations\AbstractPostgreSQLMigration;

final class Version20260720160025 extends AbstractPostgreSQLMigration
{
    public function getDescription(): string
    {
        return 'Creates the emagma_order_withdrawal_request table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE emagma_order_withdrawal_request_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE emagma_order_withdrawal_request (id INT NOT NULL, channel_id INT DEFAULT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, order_number VARCHAR(255) NOT NULL, order_date DATE NOT NULL, not_yet_received BOOLEAN NOT NULL, reception_date DATE DEFAULT NULL, email VARCHAR(255) NOT NULL, scope VARCHAR(255) NOT NULL, message TEXT DEFAULT NULL, legal_notice_accepted BOOLEAN NOT NULL, state VARCHAR(255) NOT NULL, submitted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, locale_code VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_BBBAFE9472F5A1AA ON emagma_order_withdrawal_request (channel_id)');
        $this->addSql('CREATE INDEX IDX_emagma_withdrawal_state_submitted_at ON emagma_order_withdrawal_request (state, submitted_at)');
        $this->addSql('COMMENT ON COLUMN emagma_order_withdrawal_request.order_date IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN emagma_order_withdrawal_request.reception_date IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN emagma_order_withdrawal_request.submitted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN emagma_order_withdrawal_request.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE emagma_order_withdrawal_request ADD CONSTRAINT FK_BBBAFE9472F5A1AA FOREIGN KEY (channel_id) REFERENCES sylius_channel (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE emagma_order_withdrawal_request DROP CONSTRAINT FK_BBBAFE9472F5A1AA');
        $this->addSql('DROP TABLE emagma_order_withdrawal_request');
        $this->addSql('DROP SEQUENCE emagma_order_withdrawal_request_id_seq CASCADE');
    }
}
