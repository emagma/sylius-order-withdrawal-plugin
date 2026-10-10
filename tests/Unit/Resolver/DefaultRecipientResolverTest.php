<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\Resolver;

use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\Resolver\DefaultRecipientResolver;
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

    public function testItFallsBackWhenTheChannelContactEmailIsAnEmptyString(): void
    {
        $resolver = new DefaultRecipientResolver('notifications@shop.test');

        self::assertSame(['notifications@shop.test'], $resolver->getRecipients($this->requestForChannel('')));
    }

    /**
     * An unset notification_email reaches the resolver as null — and so does a
     * `%env(default::…)%` placeholder whose variable is not set, which is why the
     * constructor is nullable rather than defaulting to an empty string.
     */
    public function testItReturnsNoRecipientWhenNeitherTheChannelNorTheConfigurationHasAnAddress(): void
    {
        $resolver = new DefaultRecipientResolver(null);

        self::assertSame([], $resolver->getRecipients($this->requestForChannel(null)));
        self::assertSame([], $resolver->getRecipients(new WithdrawalRequest()));
    }

    public function testItStillPrefersTheChannelContactEmailWithoutAConfiguredAddress(): void
    {
        $resolver = new DefaultRecipientResolver(null);

        self::assertSame(['sav@brand.test'], $resolver->getRecipients($this->requestForChannel('sav@brand.test')));
    }

    public function testItTreatsABlankConfiguredAddressAsNoAddress(): void
    {
        $resolver = new DefaultRecipientResolver('   ');

        self::assertSame([], $resolver->getRecipients(new WithdrawalRequest()));
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
