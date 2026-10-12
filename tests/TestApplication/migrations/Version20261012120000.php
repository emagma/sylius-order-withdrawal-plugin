<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\TestApplication\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Schema API rather than raw SQL: one migration covers MySQL, MariaDB and PostgreSQL.
 */
final class Version20261012120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the test application\'s internal_note column to emagma_order_withdrawal_request.';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('emagma_order_withdrawal_request')
            ->addColumn('internal_note', Types::TEXT, ['notnull' => false])
        ;
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('emagma_order_withdrawal_request')->dropColumn('internal_note');
    }
}
