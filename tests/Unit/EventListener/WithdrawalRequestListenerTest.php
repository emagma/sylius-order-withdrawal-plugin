<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\EventListener;

use DateTimeImmutable;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\Event\WithdrawalRequestSubmitted;
use Emagma\SyliusOrderWithdrawalPlugin\EventListener\WithdrawalRequestListener;
use Emagma\SyliusOrderWithdrawalPlugin\Mailer\WithdrawalRequestEmailerInterface;
use Emagma\SyliusOrderWithdrawalPlugin\Notifier\WithdrawalRequestNotifierAggregatorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SM\Event\TransitionEvent;
use SM\StateMachine\StateMachineInterface;
use stdClass;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class WithdrawalRequestListenerTest extends TestCase
{
    public function testItSendsTheAcknowledgementEmailWhenEnabled(): void
    {
        $emailer = $this->createMock(WithdrawalRequestEmailerInterface::class);
        $request = new WithdrawalRequest();

        $emailer->expects(self::once())
            ->method('sendAcknowledgementEmail')
            ->with($request);

        $listener = $this->listener(emailer: $emailer, sendAcknowledgement: true);
        $listener->onPostCreate(new ResourceControllerEvent($request));
    }

    public function testItDoesNotSendTheAcknowledgementEmailWhenDisabled(): void
    {
        $emailer = $this->createMock(WithdrawalRequestEmailerInterface::class);
        $request = new WithdrawalRequest();

        $emailer->expects(self::never())
            ->method('sendAcknowledgementEmail');

        $listener = $this->listener(emailer: $emailer, sendAcknowledgement: false);
        $listener->onPostCreate(new ResourceControllerEvent($request));
    }

    public function testItStillDispatchesTheSubmittedEventWhenTheAcknowledgementEmailFails(): void
    {
        $request = new WithdrawalRequest();

        $emailer = $this->createMock(WithdrawalRequestEmailerInterface::class);
        $emailer->method('sendAcknowledgementEmail')->willThrowException(new RuntimeException('smtp down'));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(WithdrawalRequestSubmitted::class));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $listener = $this->listener(
            emailer: $emailer,
            eventDispatcher: $eventDispatcher,
            sendAcknowledgement: true,
            logger: $logger,
        );
        $listener->onPostCreate(new ResourceControllerEvent($request));
    }

    public function testItStillDispatchesTheSubmittedEventWhenTheNotifierFails(): void
    {
        $request = new WithdrawalRequest();

        $notifier = $this->createMock(WithdrawalRequestNotifierAggregatorInterface::class);
        $notifier->method('notify')->willThrowException(new RuntimeException('notifier down'));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(WithdrawalRequestSubmitted::class));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $listener = $this->listener(
            notifier: $notifier,
            eventDispatcher: $eventDispatcher,
            logger: $logger,
        );
        $listener->onPostCreate(new ResourceControllerEvent($request));
    }

    public function testItDoesNotPropagateWhenTheSubmittedEventDispatchFails(): void
    {
        $request = new WithdrawalRequest();

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willThrowException(new RuntimeException('listener exploded'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $listener = $this->listener(eventDispatcher: $eventDispatcher, logger: $logger);
        $listener->onPostCreate(new ResourceControllerEvent($request));

        self::assertTrue(true, 'onPostCreate() must not let a failing event dispatch propagate.');
    }

    public function testItDefaultsSubmissionMetadataOnCreateWhenAbsent(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);

        $localeContext = $this->createMock(LocaleContextInterface::class);
        $localeContext->method('getLocaleCode')->willReturn('fr_FR');

        $listener = $this->listener(channelContext: $channelContext, localeContext: $localeContext);

        $request = new WithdrawalRequest();
        $listener->onPreCreate(new ResourceControllerEvent($request));

        self::assertInstanceOf(DateTimeImmutable::class, $request->getSubmittedAt());
        self::assertSame($channel, $request->getChannel());
        self::assertSame('fr_FR', $request->getLocaleCode());
    }

    public function testItStampsTheSubmissionWithTheInjectedClock(): void
    {
        $clock = $this->fixedClock('2026-03-04 09:30:00');
        $listener = $this->listener(clock: $clock);

        $request = new WithdrawalRequest();
        $listener->onPreCreate(new ResourceControllerEvent($request));

        self::assertEquals(new DateTimeImmutable('2026-03-04 09:30:00'), $request->getSubmittedAt());
    }

    public function testItKeepsMetadataThatIsAlreadySet(): void
    {
        $submittedAt = new DateTimeImmutable('2026-01-01 10:00:00');
        $ownChannel = $this->createMock(ChannelInterface::class);

        $otherChannel = $this->createMock(ChannelInterface::class);
        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($otherChannel);

        $localeContext = $this->createMock(LocaleContextInterface::class);
        $localeContext->method('getLocaleCode')->willReturn('es_ES');

        $listener = $this->listener(channelContext: $channelContext, localeContext: $localeContext);

        $request = new WithdrawalRequest();
        $request->setSubmittedAt($submittedAt);
        $request->setChannel($ownChannel);
        $request->setLocaleCode('en_US');

        $listener->onPreCreate(new ResourceControllerEvent($request));

        self::assertSame($submittedAt, $request->getSubmittedAt());
        self::assertSame($ownChannel, $request->getChannel());
        self::assertSame('en_US', $request->getLocaleCode());
    }

    public function testItLeavesTheChannelNullWhenThereIsNoAmbientChannel(): void
    {
        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willThrowException(new ChannelNotFoundException());

        $listener = $this->listener(channelContext: $channelContext);

        $request = new WithdrawalRequest();
        $listener->onPreCreate(new ResourceControllerEvent($request));

        self::assertNull($request->getChannel());
    }

    public function testItDispatchesTheSubmittedEventCarryingTheSameRequestInstance(): void
    {
        $request = new WithdrawalRequest();

        $eventDispatcher = new EventDispatcher();
        $dispatched = null;
        $eventDispatcher->addListener(
            WithdrawalRequestSubmitted::class,
            static function (WithdrawalRequestSubmitted $event) use (&$dispatched): void {
                $dispatched = $event;
            },
        );

        $listener = $this->listener(eventDispatcher: $eventDispatcher);
        $listener->onPostCreate(new ResourceControllerEvent($request));

        self::assertInstanceOf(WithdrawalRequestSubmitted::class, $dispatched);
        self::assertSame($request, $dispatched->getRequest());
    }

    public function testItTouchesTheRequestOnEveryAppliedTransitionEvenWhenNotConfirm(): void
    {
        $emailer = $this->createMock(WithdrawalRequestEmailerInterface::class);
        $emailer->expects(self::never())->method('sendConfirmationEmail');

        $listener = $this->listener(emailer: $emailer);

        $request = new WithdrawalRequest();
        self::assertNull($request->getUpdatedAt());

        $listener->onTransitionApplied($this->transitionEvent('start', $request));

        self::assertInstanceOf(DateTimeImmutable::class, $request->getUpdatedAt());
    }

    public function testItStampsTheAppliedTransitionWithTheInjectedClock(): void
    {
        $clock = $this->fixedClock('2026-03-04 09:30:00');
        $emailer = $this->createMock(WithdrawalRequestEmailerInterface::class);

        $listener = $this->listener(emailer: $emailer, clock: $clock);

        $request = new WithdrawalRequest();

        $listener->onTransitionApplied($this->transitionEvent('start', $request));

        self::assertEquals(new DateTimeImmutable('2026-03-04 09:30:00'), $request->getUpdatedAt());
    }

    /**
     * POST_TRANSITION is dispatched for every winzou graph in the application — Sylius core
     * alone ships eight — so a transition on someone else's graph must be ignored outright.
     */
    public function testItIgnoresTransitionsFromAnotherGraph(): void
    {
        $emailer = $this->createMock(WithdrawalRequestEmailerInterface::class);
        $emailer->expects(self::never())->method('sendConfirmationEmail');

        $listener = $this->listener(emailer: $emailer);

        $request = new WithdrawalRequest();

        $listener->onTransitionApplied($this->transitionEvent('confirm', $request, 'sylius_order'));
        $listener->onPostConfirm(new ResourceControllerEvent($request));

        self::assertNull($request->getUpdatedAt(), 'Another graph must not stamp the request.');
    }

    public function testItIgnoresASubjectThatIsNotAWithdrawalRequest(): void
    {
        $emailer = $this->createMock(WithdrawalRequestEmailerInterface::class);
        $emailer->expects(self::never())->method('sendConfirmationEmail');

        $listener = $this->listener(emailer: $emailer);

        $listener->onTransitionApplied($this->transitionEvent('confirm', new stdClass()));
        $listener->onPostConfirm(new ResourceControllerEvent(new WithdrawalRequest()));
    }

    public function testItSendsTheConfirmationEmailOnlyWhenTheConfirmTransitionIsApplied(): void
    {
        $emailer = $this->createMock(WithdrawalRequestEmailerInterface::class);
        $request = new WithdrawalRequest();
        $emailer->expects(self::once())->method('sendConfirmationEmail')->with($request);

        $listener = $this->listener(emailer: $emailer);

        $listener->onTransitionApplied($this->transitionEvent('confirm', $request));

        self::assertInstanceOf(DateTimeImmutable::class, $request->getUpdatedAt());

        // The confirmation email is deferred until the flush actually happens.
        $listener->onPostConfirm(new ResourceControllerEvent($request));
    }

    public function testItDefersTheConfirmationEmailUntilAfterTheUpdateIsFlushed(): void
    {
        $request = new WithdrawalRequest();

        $sent = false;

        $emailer = $this->createMock(WithdrawalRequestEmailerInterface::class);
        $emailer->expects($this->once())
            ->method('sendConfirmationEmail')
            ->with($request)
            ->willReturnCallback(function () use (&$sent): void {
                $sent = true;
            });

        $listener = $this->listener(emailer: $emailer);

        $listener->onTransitionApplied($this->transitionEvent('confirm', $request));

        // Nothing is sent yet — the flush has not happened.
        self::assertFalse($sent, 'The confirmation email must not be sent before onPostConfirm runs.');

        $listener->onPostConfirm(new ResourceControllerEvent($request));

        self::assertTrue($sent, 'The confirmation email must be sent once onPostConfirm runs.');
    }

    public function testItDoesNotSendAConfirmationForOtherTransitions(): void
    {
        $emailer = $this->createMock(WithdrawalRequestEmailerInterface::class);
        $emailer->expects($this->never())->method('sendConfirmationEmail');

        $listener = $this->listener(emailer: $emailer);

        $listener->onTransitionApplied($this->transitionEvent('reject', new WithdrawalRequest()));
        $listener->onPostConfirm(new ResourceControllerEvent(new WithdrawalRequest()));
    }

    public function testItClearsThePendingConfirmationSoALaterUpdateDoesNotResendIt(): void
    {
        $confirmed = new WithdrawalRequest();

        $emailer = $this->createMock(WithdrawalRequestEmailerInterface::class);
        $emailer->expects(self::once())->method('sendConfirmationEmail')->with($confirmed);

        $listener = $this->listener(emailer: $emailer);

        $listener->onTransitionApplied($this->transitionEvent('confirm', $confirmed));
        $listener->onPostConfirm(new ResourceControllerEvent($confirmed));

        // A later, unrelated post_update (e.g. for a different request's edit) must not
        // resend the confirmation already flushed above.
        $listener->onPostConfirm(new ResourceControllerEvent(new WithdrawalRequest()));
    }

    private function transitionEvent(
        string $transition,
        object $subject,
        string $graph = WithdrawalRequestListener::GRAPH,
    ): TransitionEvent {
        $stateMachine = $this->createMock(StateMachineInterface::class);
        $stateMachine->method('getGraph')->willReturn($graph);
        $stateMachine->method('getObject')->willReturn($subject);

        return new TransitionEvent($transition, 'new', [], $stateMachine);
    }

    /**
     * symfony/clock (and its MockClock) is Symfony 6.2+, which the 5.4 floor of this line does
     * not reach — psr/clock is the only contract available here.
     */
    private function fixedClock(string $at): ClockInterface
    {
        return new class($at) implements ClockInterface {
            public function __construct(private readonly string $at)
            {
            }

            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable($this->at);
            }
        };
    }

    private function listener(
        ?WithdrawalRequestEmailerInterface $emailer = null,
        ?WithdrawalRequestNotifierAggregatorInterface $notifier = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?ChannelContextInterface $channelContext = null,
        ?LocaleContextInterface $localeContext = null,
        bool $sendAcknowledgement = true,
        ?LoggerInterface $logger = null,
        ?ClockInterface $clock = null,
    ): WithdrawalRequestListener {
        return new WithdrawalRequestListener(
            $emailer ?? $this->createMock(WithdrawalRequestEmailerInterface::class),
            $notifier ?? $this->createMock(WithdrawalRequestNotifierAggregatorInterface::class),
            $eventDispatcher ?? $this->createMock(EventDispatcherInterface::class),
            $channelContext ?? $this->createMock(ChannelContextInterface::class),
            $localeContext ?? $this->createMock(LocaleContextInterface::class),
            $sendAcknowledgement,
            $logger,
            $clock,
        );
    }
}
