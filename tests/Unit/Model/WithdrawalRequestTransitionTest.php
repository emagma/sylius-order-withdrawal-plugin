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
    private const WORKFLOW_FILE = __DIR__ . '/../../../config/workflow/state_machine.yaml';

    public function testTheGraphAndTheCasesKeepTheirPublishedValues(): void
    {
        self::assertSame('emagma_sylius_order_withdrawal_request', WithdrawalRequestTransition::GRAPH);
        self::assertSame('start', WithdrawalRequestTransition::Start->value);
        self::assertSame('confirm', WithdrawalRequestTransition::Confirm->value);
        self::assertSame('reject', WithdrawalRequestTransition::Reject->value);
    }

    public function testTheCasesAreExactlyTheTransitionsOfTheShippedGraph(): void
    {
        $workflows = Yaml::parseFile(self::WORKFLOW_FILE)['framework']['workflows'] ?? [];

        self::assertArrayHasKey(WithdrawalRequestTransition::GRAPH, $workflows);
        $caseValues = array_map(
            static fn (WithdrawalRequestTransition $case): string => $case->value,
            WithdrawalRequestTransition::cases(),
        );

        self::assertSame($caseValues, array_keys($workflows[WithdrawalRequestTransition::GRAPH]['transitions']));
    }
}
