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

final readonly class ChainWithdrawalRequestNotifier implements WithdrawalRequestNotifierAggregatorInterface
{
    /**
     * @param iterable<WithdrawalRequestNotifierInterface> $notifiers
     */
    public function __construct(private iterable $notifiers)
    {
    }

    public function notify(WithdrawalRequest $request): void
    {
        foreach ($this->notifiers as $notifier) {
            $notifier->notify($request);
        }
    }
}
