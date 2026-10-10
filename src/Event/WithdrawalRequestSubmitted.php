<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\Event;

use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;

final readonly class WithdrawalRequestSubmitted
{
    public function __construct(private WithdrawalRequest $request)
    {
    }

    public function getRequest(): WithdrawalRequest
    {
        return $this->request;
    }
}
