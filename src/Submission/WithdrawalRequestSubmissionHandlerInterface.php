<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\Submission;

use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;

interface WithdrawalRequestSubmissionHandlerInterface
{
    public function prepare(WithdrawalRequest $request): void;

    public function complete(WithdrawalRequest $request): void;
}
