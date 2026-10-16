<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\EventListener;

use Emagma\SyliusOrderWithdrawalPlugin\Confirmation\PendingConfirmationQueueInterface;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\EventListener\WithdrawalRequestListener;
use Emagma\SyliusOrderWithdrawalPlugin\Mailer\WithdrawalRequestEmailerInterface;
use Emagma\SyliusOrderWithdrawalPlugin\Notifier\WithdrawalRequestNotifierAggregatorInterface;
use Emagma\SyliusOrderWithdrawalPlugin\Submission\WithdrawalRequestSubmissionHandlerInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class WithdrawalRequestListenerDelegationTest extends TestCase
{
    public function testPreCreateAndPostCreateDelegateToTheSubmissionHandler(): void
    {
        $request = new WithdrawalRequest();
        $handler = $this->createMock(WithdrawalRequestSubmissionHandlerInterface::class);
        $handler->expects(self::once())->method('prepare')->with($request);
        $handler->expects(self::once())->method('complete')->with($request);

        $listener = $this->listener(handler: $handler);
        $listener->onPreCreate(new ResourceControllerEvent($request));
        $listener->onPostCreate(new ResourceControllerEvent($request));
    }

    public function testTheConfirmTransitionGoesThroughTheSharedQueue(): void
    {
        $request = new WithdrawalRequest();
        $queue = $this->createMock(PendingConfirmationQueueInterface::class);
        $queue->expects(self::once())->method('enqueue')->with($request);
        $queue->expects(self::once())->method('drain');

        $listener = $this->listener(queue: $queue);
        $listener->onTransitionCompleted(new CompletedEvent($request, new Marking(), new Transition('confirm', 'new', 'confirmed')));
        $listener->onPostConfirm(new ResourceControllerEvent($request));
    }

    public function testResettingTheListenerResetsItsQueue(): void
    {
        $queue = $this->createMock(PendingConfirmationQueueInterface::class);
        $queue->expects(self::once())->method('reset');

        $this->listener(queue: $queue)->reset();
    }

    public function testOtherTransitionsAreNotQueued(): void
    {
        $queue = $this->createMock(PendingConfirmationQueueInterface::class);
        $queue->expects(self::never())->method('enqueue');

        $this->listener(queue: $queue)
            ->onTransitionCompleted(new CompletedEvent(new WithdrawalRequest(), new Marking(), new Transition('start', 'new', 'in_progress')));
    }

    private function listener(
        ?WithdrawalRequestSubmissionHandlerInterface $handler = null,
        ?PendingConfirmationQueueInterface $queue = null,
    ): WithdrawalRequestListener {
        return new WithdrawalRequestListener(
            $this->createMock(WithdrawalRequestEmailerInterface::class),
            $this->createMock(WithdrawalRequestNotifierAggregatorInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(ChannelContextInterface::class),
            $this->createMock(LocaleContextInterface::class),
            true,
            null,
            null,
            $handler ?? $this->createMock(WithdrawalRequestSubmissionHandlerInterface::class),
            $queue ?? $this->createMock(PendingConfirmationQueueInterface::class),
        );
    }
}
