<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\StateMachine;

use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Sylius\Abstraction\StateMachine\Exception\StateMachineExecutionException;

interface WithdrawalRequestTransitionApplierInterface
{
    /** @throws StateMachineExecutionException when the current state does not allow $transition */
    public function apply(WithdrawalRequest $request, string $transition): void;

    /** @return list<string> */
    public function getAvailableTransitions(WithdrawalRequest $request): array;
}
