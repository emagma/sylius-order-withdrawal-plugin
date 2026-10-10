<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\Resolver;

use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;

interface RecipientResolverInterface
{
    /**
     * @return string[]
     */
    public function getRecipients(WithdrawalRequest $request): array;
}
