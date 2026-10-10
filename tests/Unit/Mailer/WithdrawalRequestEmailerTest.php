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
use Emagma\SyliusOrderWithdrawalPlugin\Resolver\WithdrawalRequestContextResolverInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
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

        $emailer = new WithdrawalRequestEmailer($sender, $this->contextResolver(ambientChannel: $channel, ambientLocaleCode: 'en_US'));
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

        $emailer = new WithdrawalRequestEmailer($sender, $this->contextResolver(ambientChannel: $ambientChannel, ambientLocaleCode: 'en_US'));
        $emailer->sendConfirmationEmail($request);
    }

    public function testItFallsBackToNoChannelWhenTheRequestHasNoneAndTheAmbientChannelCannotBeResolved(): void
    {
        $sender = $this->createMock(SenderInterface::class);
        $request = new WithdrawalRequest();
        $request->setEmail('jean@example.com');

        $sender->expects($this->once())
            ->method('send')
            ->with(
                'emagma_withdrawal_request_confirmation',
                ['jean@example.com'],
                ['withdrawalRequest' => $request, 'channel' => null, 'localeCode' => 'en_US'],
            );

        $emailer = new WithdrawalRequestEmailer($sender, $this->contextResolver(ambientChannel: null, ambientLocaleCode: 'en_US'));
        $emailer->sendConfirmationEmail($request);
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
