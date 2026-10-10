<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\Notifier;

use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Psr\Log\LoggerInterface;
use Throwable;

final class ChainWithdrawalRequestNotifier implements WithdrawalRequestNotifierAggregatorInterface
{
    /**
     * @param iterable<WithdrawalRequestNotifierInterface> $notifiers
     */
    public function __construct(
        private readonly iterable $notifiers,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * A withdrawal request is already persisted by the time the chain runs, so one failing
     * channel must not suppress the others nor fail the customer's request.
     */
    public function notify(WithdrawalRequest $request): void
    {
        foreach ($this->notifiers as $notifier) {
            try {
                $notifier->notify($request);
            } catch (Throwable $exception) {
                $this->logger?->error('Withdrawal request notifier failed.', [
                    'notifier' => $notifier::class,
                    'withdrawalRequest' => $request->getId(),
                    'exception' => $exception,
                ]);
            }
        }
    }
}
