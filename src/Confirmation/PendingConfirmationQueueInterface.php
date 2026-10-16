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
use Symfony\Contracts\Service\ResetInterface;

interface PendingConfirmationQueueInterface extends ResetInterface
{
    public function enqueue(WithdrawalRequest $request): void;

    public function drain(): void;

    public function reset(): void;
}
