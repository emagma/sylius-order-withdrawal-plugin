<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\Confirmation;

use Emagma\SyliusOrderWithdrawalPlugin\Confirmation\PendingConfirmationQueue;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\Mailer\WithdrawalRequestEmailerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class PendingConfirmationQueueTest extends TestCase
{
    public function testDrainSendsOneConfirmationPerQueuedRequest(): void
    {
        $first = new WithdrawalRequest();
        $second = new WithdrawalRequest();

        $sent = [];
        $emailer = $this->createMock(WithdrawalRequestEmailerInterface::class);
        $emailer->expects(self::exactly(2))
            ->method('sendConfirmationEmail')
            ->willReturnCallback(static function (WithdrawalRequest $request) use (&$sent): void {
                $sent[] = $request;
            });

        $queue = new PendingConfirmationQueue($emailer);
        $queue->enqueue($first);
        $queue->enqueue($second);
        $queue->drain();

        self::assertSame([$first, $second], $sent);
    }

    public function testDrainEmptiesTheQueueSoNothingIsResent(): void
    {
        $emailer = $this->createMock(WithdrawalRequestEmailerInterface::class);
        $emailer->expects(self::once())->method('sendConfirmationEmail');

        $queue = new PendingConfirmationQueue($emailer);
        $queue->enqueue(new WithdrawalRequest());
        $queue->drain();
        $queue->drain();
    }

    public function testAFailingEmailIsLoggedAndDoesNotStopTheOthers(): void
    {
        $emailer = $this->createMock(WithdrawalRequestEmailerInterface::class);
        $emailer->expects(self::exactly(2))
            ->method('sendConfirmationEmail')
            ->willReturnOnConsecutiveCalls(self::throwException(new RuntimeException('smtp down')), null);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $queue = new PendingConfirmationQueue($emailer, $logger);
        $queue->enqueue(new WithdrawalRequest());
        $queue->enqueue(new WithdrawalRequest());
        $queue->drain();
    }

    public function testResetDropsPendingRequestsWithoutSending(): void
    {
        $emailer = $this->createMock(WithdrawalRequestEmailerInterface::class);
        $emailer->expects(self::never())->method('sendConfirmationEmail');

        $queue = new PendingConfirmationQueue($emailer);
        $queue->enqueue(new WithdrawalRequest());
        $queue->reset();
        $queue->drain();
    }
}
