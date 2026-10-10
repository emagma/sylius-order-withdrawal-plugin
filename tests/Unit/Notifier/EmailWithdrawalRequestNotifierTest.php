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
use Emagma\SyliusOrderWithdrawalPlugin\Notifier\EmailWithdrawalRequestNotifier;
use Emagma\SyliusOrderWithdrawalPlugin\Resolver\RecipientResolverInterface;
use Emagma\SyliusOrderWithdrawalPlugin\Resolver\WithdrawalRequestContextResolverInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;

final class EmailWithdrawalRequestNotifierTest extends TestCase
{
    public function testItEmailsTheResolvedRecipients(): void
    {
        $sender = $this->createMock(SenderInterface::class);
        $resolver = $this->createMock(RecipientResolverInterface::class);
        $channel = $this->createMock(ChannelInterface::class);
        $request = new WithdrawalRequest();
        $resolver->method('getRecipients')->with($request)->willReturn(['notifications@shop.test']);

        $sender->expects($this->once())
            ->method('send')
            ->with(
                'emagma_withdrawal_request_notification',
                ['notifications@shop.test'],
                ['withdrawalRequest' => $request, 'channel' => $channel, 'localeCode' => 'en_US'],
            );

        $notifier = new EmailWithdrawalRequestNotifier($sender, $resolver, $this->contextResolver(ambientChannel: $channel, ambientLocaleCode: 'en_US'));
        $notifier->notify($request);
    }

    public function testItDoesNothingWhenThereAreNoRecipients(): void
    {
        $sender = $this->createMock(SenderInterface::class);
        $resolver = $this->createMock(RecipientResolverInterface::class);
        $request = new WithdrawalRequest();
        $resolver->method('getRecipients')->with($request)->willReturn([]);

        $sender->expects($this->never())->method('send');

        $notifier = new EmailWithdrawalRequestNotifier(
            $sender,
            $resolver,
            $this->createMock(WithdrawalRequestContextResolverInterface::class),
        );
        $notifier->notify($request);
    }

    public function testItRendersWithTheChannelTheRequestWasSubmittedOn(): void
    {
        $submittedChannel = $this->createMock(ChannelInterface::class);
        $ambientChannel = $this->createMock(ChannelInterface::class);

        $request = new WithdrawalRequest();
        $request->setChannel($submittedChannel);
        $request->setLocaleCode('fr_FR');

        $sender = $this->createMock(SenderInterface::class);
        $sender->expects($this->once())->method('send')->with(
            EmailWithdrawalRequestNotifier::NOTIFICATION,
            ['sav@example.com'],
            $this->callback(static fn (array $context): bool => $context['channel'] === $submittedChannel && 'fr_FR' === $context['localeCode']),
        );

        $notifier = new EmailWithdrawalRequestNotifier(
            $sender,
            $this->recipientResolver('sav@example.com'),
            $this->contextResolver(ambientChannel: $ambientChannel, ambientLocaleCode: 'en_US'),
        );

        $notifier->notify($request);
    }

    public function testItDoesNotThrowWhenThereIsNoAmbientChannel(): void
    {
        $notifier = new EmailWithdrawalRequestNotifier(
            $this->createMock(SenderInterface::class),
            $this->recipientResolver('sav@example.com'),
            $this->contextResolver(ambientChannel: null, ambientLocaleCode: 'en_US'),
        );

        $notifier->notify(new WithdrawalRequest());

        $this->expectNotToPerformAssertions();
    }

    private function recipientResolver(string $recipient): RecipientResolverInterface
    {
        $resolver = $this->createMock(RecipientResolverInterface::class);
        $resolver->method('getRecipients')->willReturn([$recipient]);

        return $resolver;
    }

    /**
     * Mirrors the real resolver: the request's own channel/locale win, the ambient ones are the
     * fallback, and a missing ambient channel resolves to null rather than throwing.
     */
    private function contextResolver(?ChannelInterface $ambientChannel, string $ambientLocaleCode): WithdrawalRequestContextResolverInterface
    {
        $resolver = $this->createMock(WithdrawalRequestContextResolverInterface::class);
        $resolver->method('resolveChannel')->willReturnCallback(
            static fn (WithdrawalRequest $request): ?ChannelInterface => $request->getChannel() ?? $ambientChannel,
        );
        $resolver->method('resolveLocaleCode')->willReturnCallback(
            static fn (WithdrawalRequest $request): string => $request->getLocaleCode() ?? $ambientLocaleCode,
        );

        return $resolver;
    }
}
