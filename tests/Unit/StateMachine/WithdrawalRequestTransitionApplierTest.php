<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\StateMachine;

use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\StateMachine\WithdrawalRequestTransitionApplier;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\Exception\StateMachineExecutionException;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Abstraction\StateMachine\Transition;

final class WithdrawalRequestTransitionApplierTest extends TestCase
{
    public function testItAppliesAnAllowedTransitionOnThePluginGraph(): void
    {
        $request = new WithdrawalRequest();
        $stateMachine = $this->createMock(StateMachineInterface::class);
        $stateMachine->method('can')->with($request, 'emagma_sylius_order_withdrawal_request', 'confirm')->willReturn(true);
        $stateMachine->expects(self::once())->method('apply')->with($request, 'emagma_sylius_order_withdrawal_request', 'confirm');

        (new WithdrawalRequestTransitionApplier($stateMachine))->apply($request, 'confirm');
    }

    public function testItRefusesATransitionTheCurrentStateDoesNotAllow(): void
    {
        $request = new WithdrawalRequest();
        $request->setState('rejected');

        $stateMachine = $this->createMock(StateMachineInterface::class);
        $stateMachine->method('can')->willReturn(false);
        $stateMachine->expects(self::never())->method('apply');

        $this->expectException(StateMachineExecutionException::class);
        $this->expectExceptionMessage('Cannot apply transition "confirm" to a withdrawal request in state "rejected".');

        (new WithdrawalRequestTransitionApplier($stateMachine))->apply($request, 'confirm');
    }

    public function testItListsTheEnabledTransitionNames(): void
    {
        $request = new WithdrawalRequest();
        $stateMachine = $this->createMock(StateMachineInterface::class);
        $stateMachine->method('getEnabledTransitions')->with($request, 'emagma_sylius_order_withdrawal_request')->willReturn([
            new Transition('start', ['new'], ['in_progress']),
            new Transition('confirm', ['new'], ['confirmed']),
            new Transition('reject', ['new'], ['rejected']),
        ]);

        self::assertSame(['start', 'confirm', 'reject'], (new WithdrawalRequestTransitionApplier($stateMachine))->getAvailableTransitions($request));
    }
}
