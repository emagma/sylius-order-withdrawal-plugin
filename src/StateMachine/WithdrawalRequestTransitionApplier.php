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
use Emagma\SyliusOrderWithdrawalPlugin\Model\WithdrawalRequestTransition;
use function sprintf;
use Sylius\Abstraction\StateMachine\Exception\StateMachineExecutionException;
use Sylius\Abstraction\StateMachine\StateMachineInterface;

/**
 * @internal
 */
final readonly class WithdrawalRequestTransitionApplier implements WithdrawalRequestTransitionApplierInterface
{
    public function __construct(private StateMachineInterface $stateMachine)
    {
    }

    public function apply(WithdrawalRequest $request, string $transition): void
    {
        if (!$this->stateMachine->can($request, WithdrawalRequestTransition::GRAPH, $transition)) {
            throw new StateMachineExecutionException(sprintf(
                'Cannot apply transition "%s" to a withdrawal request in state "%s".',
                $transition,
                $request->getState(),
            ));
        }

        $this->stateMachine->apply($request, WithdrawalRequestTransition::GRAPH, $transition);
    }

    public function getAvailableTransitions(WithdrawalRequest $request): array
    {
        $names = [];
        foreach ($this->stateMachine->getEnabledTransitions($request, WithdrawalRequestTransition::GRAPH) as $transition) {
            $names[] = $transition->getName();
        }

        return $names;
    }
}
