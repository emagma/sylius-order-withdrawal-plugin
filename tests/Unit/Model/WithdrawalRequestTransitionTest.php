<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\Model;

use Emagma\SyliusOrderWithdrawalPlugin\Model\WithdrawalRequestTransition;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class WithdrawalRequestTransitionTest extends TestCase
{
    private const GRAPH_FILE = __DIR__ . '/../../../config/app/state_machine.yaml';

    public function testTheGraphAndTheCasesKeepTheirPublishedValues(): void
    {
        self::assertSame('emagma_sylius_order_withdrawal_request', WithdrawalRequestTransition::GRAPH);
        self::assertSame('start', WithdrawalRequestTransition::Start->value);
        self::assertSame('confirm', WithdrawalRequestTransition::Confirm->value);
        self::assertSame('reject', WithdrawalRequestTransition::Reject->value);
    }

    public function testTheCasesAreExactlyTheTransitionsOfTheShippedGraph(): void
    {
        $graphs = Yaml::parseFile(self::GRAPH_FILE)['winzou_state_machine'] ?? [];

        self::assertArrayHasKey(WithdrawalRequestTransition::GRAPH, $graphs);
        $caseValues = array_map(
            static fn (WithdrawalRequestTransition $case): string => $case->value,
            WithdrawalRequestTransition::cases(),
        );

        self::assertSame($caseValues, array_keys($graphs[WithdrawalRequestTransition::GRAPH]['transitions']));
    }
}
