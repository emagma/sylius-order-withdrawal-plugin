<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\Api\Controller;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Emagma\SyliusOrderWithdrawalPlugin\Confirmation\PendingConfirmationQueueInterface;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\Model\WithdrawalRequestTransition;
use Emagma\SyliusOrderWithdrawalPlugin\StateMachine\WithdrawalRequestTransitionApplierInterface;
use LogicException;
use function sprintf;
use Throwable;

/** @internal */
final class ApplyWithdrawalRequestTransitionAction
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly WithdrawalRequestTransitionApplierInterface $transitionApplier,
        private readonly PendingConfirmationQueueInterface $confirmationQueue,
    ) {
    }

    public function start(WithdrawalRequest $data): WithdrawalRequest
    {
        return $this->apply($data, WithdrawalRequestTransition::Start->value);
    }

    public function confirm(WithdrawalRequest $data): WithdrawalRequest
    {
        return $this->apply($data, WithdrawalRequestTransition::Confirm->value);
    }

    public function reject(WithdrawalRequest $data): WithdrawalRequest
    {
        return $this->apply($data, WithdrawalRequestTransition::Reject->value);
    }

    private function apply(WithdrawalRequest $data, string $transition): WithdrawalRequest
    {
        $manager = $this->managerRegistry->getManagerForClass($data::class);
        if (!$manager instanceof EntityManagerInterface) {
            throw new LogicException(sprintf('No Doctrine ORM entity manager handles "%s".', $data::class));
        }

        $connection = $manager->getConnection();
        // Explicit calls, not wrapInTransaction(): it closes the EntityManager on any exception (ORM 2).
        $connection->beginTransaction();

        try {
            $manager->refresh($data, LockMode::PESSIMISTIC_WRITE);
            $this->transitionApplier->apply($data, $transition);
            $manager->flush();
            $connection->commit();
        } catch (Throwable $exception) {
            $this->confirmationQueue->reset();

            try {
                if ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }
            } catch (Throwable) {
                // The original failure is what the caller must see.
            }

            throw $exception;
        }

        $this->confirmationQueue->drain();

        return $data;
    }
}
