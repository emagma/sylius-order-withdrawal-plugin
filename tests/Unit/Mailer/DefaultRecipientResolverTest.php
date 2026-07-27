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
use Emagma\SyliusOrderWithdrawalPlugin\Mailer\DefaultRecipientResolver;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;

final class DefaultRecipientResolverTest extends TestCase
{
    public function testItReturnsTheConfiguredNotificationAddressWhenThereIsNoChannel(): void
    {
        $resolver = new DefaultRecipientResolver('notifications@shop.test');

        self::assertSame(['notifications@shop.test'], $resolver->getRecipients(new WithdrawalRequest()));
    }

    public function testItPrefersTheChannelContactEmailByDefault(): void
    {
        $resolver = new DefaultRecipientResolver('notifications@shop.test');

        self::assertSame(['sav@brand.test'], $resolver->getRecipients($this->requestForChannel('sav@brand.test')));
    }

    public function testItFallsBackToTheConfiguredAddressWhenTheChannelHasNoContactEmail(): void
    {
        $resolver = new DefaultRecipientResolver('notifications@shop.test');

        self::assertSame(['notifications@shop.test'], $resolver->getRecipients($this->requestForChannel(null)));
    }

    public function testItForcesTheConfiguredAddressWhenChannelContactEmailIsNotPreferred(): void
    {
        $resolver = new DefaultRecipientResolver('notifications@shop.test', false);

        self::assertSame(['notifications@shop.test'], $resolver->getRecipients($this->requestForChannel('sav@brand.test')));
    }

    private function requestForChannel(?string $contactEmail): WithdrawalRequest
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getContactEmail')->willReturn($contactEmail);

        $request = new WithdrawalRequest();
        $request->setChannel($channel);

        return $request;
    }
}
