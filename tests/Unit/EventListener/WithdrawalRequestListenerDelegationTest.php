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
use SM\Event\TransitionEvent;
use SM\StateMachine\StateMachineInterface;
use stdClass;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
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

    public function testCreateEventsIgnoreOtherSubjects(): void
    {
        $handler = $this->createMock(WithdrawalRequestSubmissionHandlerInterface::class);
        $handler->expects(self::never())->method('prepare');
        $handler->expects(self::never())->method('complete');

        $listener = $this->listener(handler: $handler);
        $listener->onPreCreate(new ResourceControllerEvent(new stdClass()));
        $listener->onPostCreate(new ResourceControllerEvent(new stdClass()));
    }

    public function testTheConfirmTransitionTouchesAndQueuesTheRequestOnce(): void
    {
        $request = new WithdrawalRequest();
        $queue = $this->createMock(PendingConfirmationQueueInterface::class);
        $queue->expects(self::once())->method('enqueue')->with($request);
        $queue->expects(self::once())->method('drain');

        $listener = $this->listener(queue: $queue);
        $listener->onTransitionApplied($this->transitionEvent('confirm', $request));
        $listener->onPostConfirm(new ResourceControllerEvent($request));

        self::assertNotNull($request->getUpdatedAt());
    }

    /**
     * @dataProvider provideNonConfirmTransitions
     */
    public function testOtherTransitionsTouchTheRequestWithoutQueueingIt(string $transition): void
    {
        $request = new WithdrawalRequest();
        $queue = $this->createMock(PendingConfirmationQueueInterface::class);
        $queue->expects(self::never())->method('enqueue');

        $this->listener(queue: $queue)->onTransitionApplied($this->transitionEvent($transition, $request));

        self::assertNotNull($request->getUpdatedAt());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNonConfirmTransitions(): iterable
    {
        yield 'start' => ['start'];
        yield 'reject' => ['reject'];
    }

    public function testAnotherGraphIsIgnored(): void
    {
        $request = new WithdrawalRequest();
        $queue = $this->createMock(PendingConfirmationQueueInterface::class);
        $queue->expects(self::never())->method('enqueue');

        $this->listener(queue: $queue)->onTransitionApplied($this->transitionEvent('confirm', $request, 'sylius_order'));

        self::assertNull($request->getUpdatedAt());
    }

    public function testAnotherSubjectIsIgnored(): void
    {
        $queue = $this->createMock(PendingConfirmationQueueInterface::class);
        $queue->expects(self::never())->method('enqueue');

        $this->listener(queue: $queue)->onTransitionApplied($this->transitionEvent('confirm', new stdClass()));
    }

    public function testResettingTheListenerResetsItsQueue(): void
    {
        $queue = $this->createMock(PendingConfirmationQueueInterface::class);
        $queue->expects(self::once())->method('reset');

        $this->listener(queue: $queue)->reset();
    }

    public function testConstructingWithoutTheSharedServicesIsDeprecated(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);

        try {
            new WithdrawalRequestListener(
                $this->createMock(WithdrawalRequestEmailerInterface::class),
                $this->createMock(WithdrawalRequestNotifierAggregatorInterface::class),
                $this->createMock(EventDispatcherInterface::class),
                $this->createMock(ChannelContextInterface::class),
                $this->createMock(LocaleContextInterface::class),
            );
        } finally {
            restore_error_handler();
        }

        self::assertCount(1, $deprecations);
        self::assertStringContainsString('both will be required in the next major version', $deprecations[0]);
    }

    public function testPassingTheSharedServicesIsNotDeprecated(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);

        try {
            $this->listener();
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $deprecations);
    }

    private function transitionEvent(string $transition, object $subject, string $graph = WithdrawalRequestListener::GRAPH): TransitionEvent
    {
        $stateMachine = $this->createMock(StateMachineInterface::class);
        $stateMachine->method('getObject')->willReturn($subject);
        $stateMachine->method('getGraph')->willReturn($graph);

        return new TransitionEvent($transition, 'new', ['to' => 'confirmed'], $stateMachine);
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
