<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\Submission;

use DateTimeImmutable;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\Event\WithdrawalRequestSubmitted;
use Emagma\SyliusOrderWithdrawalPlugin\Mailer\WithdrawalRequestEmailerInterface;
use Emagma\SyliusOrderWithdrawalPlugin\Notifier\WithdrawalRequestNotifierAggregatorInterface;
use Emagma\SyliusOrderWithdrawalPlugin\Submission\WithdrawalRequestSubmissionHandler;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class WithdrawalRequestSubmissionHandlerTest extends TestCase
{
    public function testPrepareDefaultsSubmissionMetadataWhenAbsent(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);

        $localeContext = $this->createMock(LocaleContextInterface::class);
        $localeContext->method('getLocaleCode')->willReturn('fr_FR');

        $clock = new MockClock(new DateTimeImmutable('2026-03-04 09:30:00'));

        $request = new WithdrawalRequest();
        $this->handler(channelContext: $channelContext, localeContext: $localeContext, clock: $clock)->prepare($request);

        self::assertEquals(new DateTimeImmutable('2026-03-04 09:30:00'), $request->getSubmittedAt());
        self::assertSame($channel, $request->getChannel());
        self::assertSame('fr_FR', $request->getLocaleCode());
    }

    public function testPrepareKeepsMetadataThatIsAlreadySet(): void
    {
        $submittedAt = new DateTimeImmutable('2026-01-01 10:00:00');
        $ownChannel = $this->createMock(ChannelInterface::class);

        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($this->createMock(ChannelInterface::class));
        $localeContext = $this->createMock(LocaleContextInterface::class);
        $localeContext->method('getLocaleCode')->willReturn('es_ES');

        $request = new WithdrawalRequest();
        $request->setSubmittedAt($submittedAt);
        $request->setChannel($ownChannel);
        $request->setLocaleCode('en_US');

        $this->handler(channelContext: $channelContext, localeContext: $localeContext)->prepare($request);

        self::assertSame($submittedAt, $request->getSubmittedAt());
        self::assertSame($ownChannel, $request->getChannel());
        self::assertSame('en_US', $request->getLocaleCode());
    }

    public function testPrepareLeavesTheChannelNullWhenThereIsNoAmbientChannel(): void
    {
        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willThrowException(new ChannelNotFoundException());

        $request = new WithdrawalRequest();
        $this->handler(channelContext: $channelContext)->prepare($request);

        self::assertNull($request->getChannel());
    }

    public function testCompleteSendsTheAcknowledgementWhenEnabled(): void
    {
        $request = new WithdrawalRequest();
        $emailer = $this->createMock(WithdrawalRequestEmailerInterface::class);
        $emailer->expects(self::once())->method('sendAcknowledgementEmail')->with($request);

        $this->handler(emailer: $emailer, sendAcknowledgement: true)->complete($request);
    }

    public function testCompleteSkipsTheAcknowledgementWhenDisabled(): void
    {
        $emailer = $this->createMock(WithdrawalRequestEmailerInterface::class);
        $emailer->expects(self::never())->method('sendAcknowledgementEmail');

        $this->handler(emailer: $emailer, sendAcknowledgement: false)->complete(new WithdrawalRequest());
    }

    public function testCompleteNotifiesAndDispatchesTheSubmittedEventForTheSameInstance(): void
    {
        $request = new WithdrawalRequest();

        $notifier = $this->createMock(WithdrawalRequestNotifierAggregatorInterface::class);
        $notifier->expects(self::once())->method('notify')->with($request);

        $dispatcher = new EventDispatcher();
        $dispatched = null;
        $dispatcher->addListener(
            WithdrawalRequestSubmitted::class,
            static function (WithdrawalRequestSubmitted $event) use (&$dispatched): void {
                $dispatched = $event;
            },
        );

        $this->handler(notifier: $notifier, eventDispatcher: $dispatcher)->complete($request);

        self::assertInstanceOf(WithdrawalRequestSubmitted::class, $dispatched);
        self::assertSame($request, $dispatched->getRequest());
    }

    public function testCompleteLogsEveryFailingStepAndRunsTheOthers(): void
    {
        $emailer = $this->createMock(WithdrawalRequestEmailerInterface::class);
        $emailer->method('sendAcknowledgementEmail')->willThrowException(new RuntimeException('smtp down'));

        $notifier = $this->createMock(WithdrawalRequestNotifierAggregatorInterface::class);
        $notifier->method('notify')->willThrowException(new RuntimeException('notifier down'));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(WithdrawalRequestSubmitted::class))
            ->willThrowException(new RuntimeException('listener exploded'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(3))->method('error');

        $this->handler(
            emailer: $emailer,
            notifier: $notifier,
            eventDispatcher: $eventDispatcher,
            logger: $logger,
        )->complete(new WithdrawalRequest());
    }

    private function handler(
        ?WithdrawalRequestEmailerInterface $emailer = null,
        ?WithdrawalRequestNotifierAggregatorInterface $notifier = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?ChannelContextInterface $channelContext = null,
        ?LocaleContextInterface $localeContext = null,
        bool $sendAcknowledgement = true,
        ?LoggerInterface $logger = null,
        ?ClockInterface $clock = null,
    ): WithdrawalRequestSubmissionHandler {
        return new WithdrawalRequestSubmissionHandler(
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
