<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\Mailer;

use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;

interface WithdrawalRequestEmailerInterface
{
    public function sendAcknowledgementEmail(WithdrawalRequest $request): void;

    public function sendConfirmationEmail(WithdrawalRequest $request): void;
}
