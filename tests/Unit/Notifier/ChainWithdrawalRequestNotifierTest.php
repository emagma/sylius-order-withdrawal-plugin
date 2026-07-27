<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\Notifier;

use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\Notifier\ChainWithdrawalRequestNotifier;
use Emagma\SyliusOrderWithdrawalPlugin\Notifier\WithdrawalRequestNotifierInterface;
use PHPUnit\Framework\TestCase;

final class ChainWithdrawalRequestNotifierTest extends TestCase
{
    public function testItForwardsTheRequestToEveryChannel(): void
    {
        $request = new WithdrawalRequest();

        $channelA = $this->createMock(WithdrawalRequestNotifierInterface::class);
        $channelA->expects($this->once())->method('notify')->with($request);

        $channelB = $this->createMock(WithdrawalRequestNotifierInterface::class);
        $channelB->expects($this->once())->method('notify')->with($request);

        $chain = new ChainWithdrawalRequestNotifier([$channelA, $channelB]);
        $chain->notify($request);
    }
}
