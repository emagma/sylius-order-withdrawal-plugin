<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\Model;

enum WithdrawalRequestTransition: string
{
    public const GRAPH = 'emagma_sylius_order_withdrawal_request';

    case Start = 'start';
    case Confirm = 'confirm';
    case Reject = 'reject';
}
