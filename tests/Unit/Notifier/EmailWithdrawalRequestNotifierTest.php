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
use Emagma\SyliusOrderWithdrawalPlugin\Mailer\RecipientResolverInterface;
use Emagma\SyliusOrderWithdrawalPlugin\Notifier\EmailWithdrawalRequestNotifier;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
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

        $notifier = new EmailWithdrawalRequestNotifier($sender, $resolver, $this->channelContext($channel), $this->localeContext());
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
            $this->createMock(ChannelContextInterface::class),
            $this->createMock(LocaleContextInterface::class),
        );
        $notifier->notify($request);
    }

    private function channelContext(ChannelInterface $channel): ChannelContextInterface
    {
        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);

        return $channelContext;
    }

    private function localeContext(): LocaleContextInterface
    {
        $localeContext = $this->createMock(LocaleContextInterface::class);
        $localeContext->method('getLocaleCode')->willReturn('en_US');

        return $localeContext;
    }
}
