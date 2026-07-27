<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\Mailer;

use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\Mailer\WithdrawalRequestEmailer;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;

final class WithdrawalRequestEmailerTest extends TestCase
{
    public function testItSendsTheAcknowledgementUsingTheAmbientChannelAndLocaleWhenTheRequestHasNone(): void
    {
        $sender = $this->createMock(SenderInterface::class);
        $channel = $this->createMock(ChannelInterface::class);
        $request = new WithdrawalRequest();
        $request->setEmail('jean@example.com');

        $sender->expects($this->once())
            ->method('send')
            ->with(
                'emagma_withdrawal_request_acknowledgement',
                ['jean@example.com'],
                ['withdrawalRequest' => $request, 'channel' => $channel, 'localeCode' => 'en_US'],
            );

        $emailer = new WithdrawalRequestEmailer($sender, $this->channelContext($channel), $this->localeContext());
        $emailer->sendAcknowledgementEmail($request);
    }

    public function testItSendsTheConfirmationUsingTheRequestsOwnChannelAndLocaleRatherThanTheAmbientOnes(): void
    {
        $sender = $this->createMock(SenderInterface::class);
        $ambientChannel = $this->createMock(ChannelInterface::class);
        $requestChannel = $this->createMock(ChannelInterface::class);
        $request = new WithdrawalRequest();
        $request->setEmail('jean@example.com');
        $request->setChannel($requestChannel);
        $request->setLocaleCode('fr_FR');

        $sender->expects($this->once())
            ->method('send')
            ->with(
                'emagma_withdrawal_request_confirmation',
                ['jean@example.com'],
                ['withdrawalRequest' => $request, 'channel' => $requestChannel, 'localeCode' => 'fr_FR'],
            );

        $emailer = new WithdrawalRequestEmailer($sender, $this->channelContext($ambientChannel), $this->localeContext());
        $emailer->sendConfirmationEmail($request);
    }

    public function testItFallsBackToNoChannelWhenTheRequestHasNoneAndTheAmbientChannelCannotBeResolved(): void
    {
        $sender = $this->createMock(SenderInterface::class);
        $request = new WithdrawalRequest();
        $request->setEmail('jean@example.com');

        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willThrowException(new ChannelNotFoundException());

        $sender->expects($this->once())
            ->method('send')
            ->with(
                'emagma_withdrawal_request_confirmation',
                ['jean@example.com'],
                ['withdrawalRequest' => $request, 'channel' => null, 'localeCode' => 'en_US'],
            );

        $emailer = new WithdrawalRequestEmailer($sender, $channelContext, $this->localeContext());
        $emailer->sendConfirmationEmail($request);
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
