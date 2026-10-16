<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\Confirmation;

use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\Mailer\WithdrawalRequestEmailerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * @internal
 */
final class PendingConfirmationQueue implements PendingConfirmationQueueInterface
{
    /** @var list<WithdrawalRequest> */
    private array $pending = [];

    public function __construct(
        private readonly WithdrawalRequestEmailerInterface $emailer,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function enqueue(WithdrawalRequest $request): void
    {
        $this->pending[] = $request;
    }

    public function drain(): void
    {
        $pending = $this->pending;
        $this->pending = [];

        foreach ($pending as $request) {
            try {
                $this->emailer->sendConfirmationEmail($request);
            } catch (Throwable $exception) {
                $this->logger?->error('Withdrawal request confirmation email failed.', [
                    'withdrawalRequest' => $request->getId(),
                    'exception' => $exception,
                ]);
            }
        }
    }

    public function reset(): void
    {
        $this->pending = [];
    }
}
